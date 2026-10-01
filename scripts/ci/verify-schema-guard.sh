#!/bin/bash
#
# verify-schema-guard.sh - snapshot the schema of a Magento database, then prove
# that a step (setup:upgrade, a module import, a migration) destroyed nothing.
# Implements task #110.
#
# WHY THIS EXISTS
# The #107 incident produced NO error at the point of damage. setup:upgrade dropped
# three mab_yalidine_* tables, the deploy carried on, and the failure surfaced later
# as an unrelated-looking DESCRIBE error. Nothing caught it: not a row count, not a
# health check, not the deploy exit code. The tables had been whitelisted as
# declarative-owned, so the module simply stopped recreating them.
#
# A guard has to FAIL LOUDLY and NAME the tables, so it exits non-zero and the
# deploy stops. It is fail-closed: if the snapshot cannot be taken, that is a
# failure, not a skip.
#
# USAGE
#   verify-schema-guard.sh snapshot --out FILE (--db NAME | --magento-root PATH)
#   verify-schema-guard.sh compare BEFORE.json AFTER.json
#
# EXIT CODES
#   0  ok (no data loss)      1  FAIL (data loss, or unable to prove otherwise)
#   2  usage / environment error
#
# Read-only: never writes to the database and never prints credentials.

set -uo pipefail

MYSQL_BIN="${MYSQL_BIN:-/opt/mariadb10.6/mariadb/bin/mysql}"
[ -x "$MYSQL_BIN" ] || MYSQL_BIN="$(command -v mysql || true)"
[ -n "$MYSQL_BIN" ] && [ -x "$MYSQL_BIN" ] || { echo "ERROR: mysql client not found" >&2; exit 2; }

# Timeout for the exact row-count sweep, so a guard can never hang a deploy.
ROWCOUNT_TIMEOUT="${ROWCOUNT_TIMEOUT:-600}"

# Credentials: environment first, then the dashboard .env, so a cron or deploy
# invocation does not have to carry them. Values are never echoed. The same
# .env loading pattern is used by scripts/migration/sync-prod-to-nonprod.sh.
ENV_FILE="${SCHEMA_GUARD_ENV_FILE:-/home/dashboard/public_html/.env}"
if [ -f "$ENV_FILE" ]; then
    while IFS='=' read -r k v; do
        case "$k" in
            DB_HOST|DB_PORT|DB_USER|DB_PASS)
                [ -n "${!k:-}" ] || export "$k=${v%$'\r'}"
                ;;
        esac
    done < "$ENV_FILE"
fi

DB_HOST="${DB_HOST:-127.0.0.1}"; DB_PORT="${DB_PORT:-3307}"; DB_USER="${DB_USER:-root}"
export MYSQL_PWD="${DB_PASS:-}"

m() { "$MYSQL_BIN" --skip-ssl -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -N -B "$@"; }
die() { echo "ERROR: $1" >&2; exit 2; }

# db name from a Magento root's env.php - 'dbname' only, never the password.
db_from_magento_root() {
    local f="$1/app/etc/env.php"
    [ -f "$f" ] || return 1
    grep -oE "'dbname'[[:space:]]*=>[[:space:]]*'[^']+'" "$f" | head -1 \
        | sed -E "s/.*'dbname'[[:space:]]*=>[[:space:]]*'([^']+)'/\1/"
}

# "name": { "rows": N, "columns": M },  ->  name<TAB>N<TAB>M
parse_snapshot() {
    sed -nE 's/^ *"([^"]+)": *\{ *"rows": *([0-9]+), *"columns": *([0-9]+) *\},?$/\1\t\2\t\3/p' "$1"
}

# --- snapshot ---------------------------------------------------------------
# One entry per base table: exact row count + column count. Written pretty-printed
# with one table per line so `compare` reads it back with awk and no JSON tool.
do_snapshot() {
    local out="$1" db="$2"
    [ -n "$db" ] || die "no database name resolved"
    [ -n "$out" ] || die "no --out file given"

    local ntables
    ntables=$(m -e "SELECT COUNT(*) FROM information_schema.tables
                    WHERE table_schema='$db' AND table_type='BASE TABLE'" 2>/dev/null)
    case "${ntables:-}" in
        ''|*[!0-9]*) die "cannot read database '$db' (check DB_HOST/DB_PORT/DB_USER/DB_PASS)" ;;
        0)          die "database '$db' has no base tables - refusing to record an empty snapshot" ;;
    esac

    local cols rows rowsf union_sql gen got
    cols=$(mktemp) || die "mktemp failed"; rowsf=$(mktemp) || die "mktemp failed"

    # Column counts come straight from the dictionary: cheap. table_type lives in
    # information_schema.TABLES, not COLUMNS, so views are excluded via a join.
    m -e "SELECT c.table_name, COUNT(*) FROM information_schema.columns c
          JOIN information_schema.tables t
            ON t.table_schema = c.table_schema AND t.table_name = c.table_name
          WHERE c.table_schema='$db' AND t.table_type='BASE TABLE'
          GROUP BY c.table_name" 2>/dev/null > "$cols"
    local ncols; ncols=$(grep -c $'\t' "$cols" 2>/dev/null || echo 0)
    [ "$ncols" = "$ntables" ] \
        || die "column-count query returned $ncols of $ntables tables (query error?)"

    # Exact row counts as a single query. information_schema.tables.table_rows is an
    # InnoDB ESTIMATE - it would both miss real losses and raise false alarms - so it
    # is not used. group_concat_max_len must be raised in the SAME session or the
    # generated UNION list is silently truncated at 1024 bytes (it reaches ~75 KB on
    # dev), which is exactly the kind of silent truncation this guard must not have.
    union_sql=$(m -e "SET SESSION group_concat_max_len=1048576;
        SELECT GROUP_CONCAT(CONCAT('SELECT ', QUOTE(table_name), ' AS t, COUNT(*) AS r FROM \`$db\`.\`', table_name, '\`')
                            SEPARATOR ' UNION ALL ')
        FROM information_schema.tables WHERE table_schema='$db' AND table_type='BASE TABLE'" 2>/dev/null)
    # -c would count LINES containing the pattern, and the whole UNION is a single
    # line, so count occurrences instead.
    gen=$(printf '%s' "$union_sql" | grep -o 'COUNT(\*)' | wc -l | tr -d ' ')
    [ "${gen:-0}" = "$ntables" ] || die "row-count query generated $gen of $ntables statements (group_concat truncation?)"

    # timeout cannot invoke a shell function, so the client is called directly here.
    # rc 124 means the sweep genuinely hit the budget; anything else is a client
    # or query error, and both are failures rather than a silent pass.
    rows=$(timeout "$ROWCOUNT_TIMEOUT" "$MYSQL_BIN" --skip-ssl -h "$DB_HOST" -P "$DB_PORT" \
             -u "$DB_USER" -N -B -e "$union_sql" 2>/dev/null)
    local rc=$?
    [ "$rc" = "124" ] && die "row-count sweep exceeded ${ROWCOUNT_TIMEOUT}s on $db"
    [ "$rc" != "0" ]   && die "row-count sweep failed (mysql exit $rc) on $db"
    [ -n "$rows" ]     || die "row-count sweep produced no output on $db"
    got=$(printf '%s\n' "$rows" | grep -c $'\t')
    [ "$got" = "$ntables" ] || die "row-count sweep returned $got of $ntables tables"

    printf '%s\n' "$rows" > "$rowsf"
    {
        printf '{\n'
        printf '  "db": "%s",\n'          "$(printf '%s' "$db" | sed 's/\\/\\\\/g; s/"/\\"/g')"
        printf '  "captured_at": "%s",\n' "$(date '+%F %T')"
        printf '  "table_count": %s,\n'   "$ntables"
        printf '  "tables": {\n'
        # A real temp file, not <(...): with an empty first file the classic
        # NR==FNR test is true for every record of the second file.
        awk -F'\t' 'NR==FNR { c[$1]=$2; n[++k]=$1; next }
                      { r[$1]=$2 }
                      END { for (i=1; i<=k; i++)
                               printf "    \"%s\": { \"rows\": %s, \"columns\": %s }%s\n",
                                      n[i], r[n[i]], (c[n[i]]=="" ? 0 : c[n[i]]), (i<k ? "," : "") }' \
            "$cols" "$rowsf"
        printf '  }\n}\n'
    } > "$out" || die "cannot write snapshot to $out"

    rm -f "$cols" "$rowsf"
    [ -s "$out" ] || die "snapshot file is empty: $out"
    return 0
}

# --- compare ----------------------------------------------------------------
#   FAIL  table disappeared     (the #107 signature)
#   FAIL  table lost rows       (truncated, or dropped and recreated empty)
#   WARN  table lost columns    (reported, non-fatal: an intentional column drop
#                                must not block a deploy the way silent data loss
#                                must; promote it to FAIL here if that changes)
# Row gains and column gains are normal for a deploy and are not reported.
do_compare() {
    local before="$1" after="$2"
    [ -f "$before" ] || die "missing snapshot: $before"
    [ -f "$after"  ] || die "missing snapshot: $after"

    local bt at diff_out
    bt=$(mktemp) || die "mktemp failed"; at=$(mktemp) || die "mktemp failed"
    diff_out=$(mktemp) || die "mktemp failed"
    parse_snapshot "$before" > "$bt"; parse_snapshot "$after" > "$at"

    local nb na
    nb=$(wc -l < "$bt"); na=$(wc -l < "$at")
    [ "$nb" -gt 0 ] || die "'$before' contains no table entries (corrupt snapshot?)"
    [ "$na" -gt 0 ] || die "'$after' contains no table entries (corrupt snapshot?)"

    # One pass over both tables. Names are emitted in `before` order so the report
    # is stable and readable.
    awk -F'\t' '
        NR==FNR { r1[$1]=$2; c1[$1]=$3; n[++k]=$1; next }
                  { r2[$1]=$2; c2[$1]=$3 }
        END {
            for (i=1; i<=k; i++) {
                t=n[i]
                if (!(t in r2)) { printf "DROP\t%s\t0\t0\n", t; continue }
                if (r2[t] < r1[t])      printf "ROWLOSS\t%s\t%s\t%s\n", t, r1[t], r2[t]
                else if (c2[t] < c1[t]) printf "COLLOSS\t%s\t%s\t%s\n", t, c1[t], c2[t]
            }
            for (t in r2) if (!(t in r1)) printf "ADD\t%s\t0\t0\n", t
        }' "$bt" "$at" > "$diff_out"

    local ndrop nrows ncols nadd
    ndrop=$(awk -F'\t' '$1=="DROP"'   "$diff_out" | wc -l)
    nrows=$(awk -F'\t' '$1=="ROWLOSS"' "$diff_out" | wc -l)
    ncols=$(awk -F'\t' '$1=="COLLOSS"' "$diff_out" | wc -l)
    nadd=$(awk  -F'\t' '$1=="ADD"'    "$diff_out" | wc -l)

    echo "=== schema guard: $(sed -nE 's/^ *"db": *"([^"]+)".*/\1/p' "$before" | head -1) ==="
    echo "  tables before: $nb    tables after: $na"
    [ "$nadd" -gt 0 ] && echo "  tables added: $nadd (expected for a schema change)"

    if [ "$ndrop" -gt 0 ]; then
        echo ""
        echo "FAIL - DROPPED TABLES ($ndrop):"
        awk -F'\t' '$1=="DROP" {printf "     - %s\n", $2}' "$diff_out"
    fi
    if [ "$nrows" -gt 0 ]; then
        echo ""
        echo "FAIL - TABLES THAT LOST ROWS ($nrows):"
        awk -F'\t' '$1=="ROWLOSS" {printf "     - %s: %s -> %s rows\n", $2, $3, $4}' "$diff_out"
    fi
    if [ "$ncols" -gt 0 ]; then
        echo ""
        echo "WARN - TABLES THAT LOST COLUMNS ($ncols, non-fatal):"
        awk -F'\t' '$1=="COLLOSS" {printf "     - %s: %s -> %s columns\n", $2, $3, $4}' "$diff_out"
    fi
    rm -f "$bt" "$at" "$diff_out"

    if [ "$ndrop" -gt 0 ] || [ "$nrows" -gt 0 ]; then
        echo ""
        echo "FAIL - schema guard tripped: the step above destroyed data. Not promoting."
        echo "  Restore before continuing: the pre-deploy backup, or re-run the deploy for"
        echo "  the module that owns the missing table. If the drop was intended, record"
        echo "  that decision explicitly rather than silencing this check."
        return 1
    fi
    echo ""
    echo "PASS - schema guard: no table disappeared and no table lost rows."
    return 0
}

# --- dispatch ---------------------------------------------------------------
cmd="${1:-}"; shift || true
case "$cmd" in
    snapshot)
        OUT=""; DB=""; ROOT=""
        while [ $# -gt 0 ]; do
            case "$1" in
                --out)          OUT="${2:-}"; shift 2 ;;
                --db)           DB="${2:-}"; shift 2 ;;
                --magento-root) ROOT="${2:-}"; shift 2 ;;
                *) die "unknown option: $1" ;;
            esac
        done
        [ -n "$OUT" ] || die "snapshot requires --out FILE"
        if [ -z "$DB" ]; then
            ROOT="${ROOT:-$PWD}"
            DB="$(db_from_magento_root "$ROOT")" || die "no app/etc/env.php under $ROOT"
            [ -n "$DB" ] || die "no dbname found in $ROOT/app/etc/env.php"
        fi
        do_snapshot "$OUT" "$DB" && echo "  schema snapshot: $OUT ($DB, $(grep -c '"rows"' "$OUT") tables)"
        ;;
    compare)
        [ $# -eq 2 ] || die "usage: $0 compare BEFORE.json AFTER.json"
        do_compare "$1" "$2"
        ;;
    *)
        sed -n '2,25p' "$0"
        exit 2
        ;;
esac
