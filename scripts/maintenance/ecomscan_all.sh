#!/bin/bash
###############################################################################
# eComscan Wrapper — Runs Sansec eComscan across all accounts
# Usage: ./ecomscan_all.sh [--account NAME] [--json]
###############################################################################

set +e

CONFIG_FILE="${ECOMSCAN_CONFIG:-/home/dashboard/.ecomscan.env}"
if [ -r "$CONFIG_FILE" ]; then
    # shellcheck disable=SC1090
    . "$CONFIG_FILE"
fi

ECOMSCAN="${ECOMSCAN_BIN:-}"
if [ -z "$ECOMSCAN" ]; then
    for candidate in "${HOME}/bin/ecomscan" "/root/bin/ecomscan" "$(command -v ecomscan 2>/dev/null)"; do
        if [ -x "$candidate" ]; then
            ECOMSCAN="$candidate"
            break
        fi
    done
fi

LICENSE_KEY="${ECOMSCAN_KEY:-}"
REPORT_EMAIL="${ECOMSCAN_REPORT_EMAIL:-}"
REPORT_DIR="/home/dashboard/public_html/reports"
LOG_DIR="/home/dashboard/public_html/logs"
mkdir -p "$REPORT_DIR" "$LOG_DIR" 2>/dev/null

ALL_ACCOUNTS=("technadminy7" "dev" "tsdnd")
TARGET_ACCOUNT=""
OUTPUT_JSON=false

while [[ $# -gt 0 ]]; do
    case "$1" in
        --json) OUTPUT_JSON=true; shift;;
        --account) TARGET_ACCOUNT="$2"; shift 2;;
        *) shift;;
    esac
done

if [ -n "$TARGET_ACCOUNT" ] && [[ ! " ${ALL_ACCOUNTS[*]} " =~ [[:space:]]${TARGET_ACCOUNT}[[:space:]] ]]; then
    echo "ERROR: unsupported account: $TARGET_ACCOUNT" >&2
    exit 2
fi

ACCOUNTS=("${TARGET_ACCOUNT:-${ALL_ACCOUNTS[@]}}")
REPORT_FILE="${REPORT_DIR}/ecomscan_$(date +%Y%m%d_%H%M%S).json"
LATEST_LINK="${REPORT_DIR}/ecomscan_latest.json"

if [ -z "$ECOMSCAN" ] || [ ! -x "$ECOMSCAN" ]; then
    echo "ERROR: ecomscan executable not found" >&2
    exit 127
fi
if [ -z "$LICENSE_KEY" ]; then
    echo "ERROR: ECOMSCAN_KEY is not configured in $CONFIG_FILE" >&2
    exit 2
fi

echo "[$(date)] Starting eComscan across: ${ACCOUNTS[*]}"

ALL_FINDINGS="[]"
ALL_ACCOUNT_SUMMARIES="[]"
TOTAL_ISSUES=0
TOTAL_CRITICAL=0
TOTAL_MALWARE=0
TOTAL_VULNS=0

for acct in "${ACCOUNTS[@]}"; do
    DOCROOT="/home/$acct/public_html"
    [ -d "$DOCROOT" ] || { echo "SKIP: $DOCROOT not found"; continue; }

    echo "[$(date)] Scanning $acct..."

    RAW_FILE="${LOG_DIR}/ecomscan_${acct}_$(date +%Y%m%d_%H%M%S).raw"
    CMD=( "$ECOMSCAN" "$DOCROOT" --key "$LICENSE_KEY" --no-auto-update )
    if [ -n "$REPORT_EMAIL" ]; then
        CMD+=( --report "$REPORT_EMAIL" )
    fi
    "${CMD[@]}" >"$RAW_FILE" 2>&1
    RESULT=$(cat "$RAW_FILE")

    if [ "$OUTPUT_JSON" = false ]; then
        echo "$RESULT" | python3 -c "
import json, sys
try:
    data = json.load(sys.stdin)
    results = data.get('check_results', [])
    detections = [r for r in results if r.get('detections')]
    print(f'  {acct}: {len(detections)} issues with detections')
    for d in detections:
        for det in d['detections']:
            cls = det.get('class', '')
            name = det.get('name', '')
            path = det.get('path', '')
            conf = det.get('confidence', 0)
            print(f'    [{cls}] {name} (conf: {conf}) {path}')
except:
    print(f'  {acct}: Failed to parse output')
" 2>/dev/null
    fi

    ACCT_SUMMARY=$(ACCT="$acct" RAW_FILE="$RAW_FILE" python3 -c "
import os, re, sys, json
text = sys.stdin.read()
def number(pattern):
    match = re.search(pattern, text, re.I)
    return int(match.group(1)) if match else 0
_report = re.search(r'Full report:\s*(\S+)', text)
print(json.dumps({
    'account': os.environ['ACCT'],
    'malware': number(r'Found:\s*(\d+)x malware'),
    'vulnerabilities': number(r'Found:\s*(\d+)x vulnerability'),
    'vulnerable_modules': number(r'Found\s+(\d+)\s+vulnerable modules'),
    'generated_changes': number(r'Found\s+(\d+)\s+changes in generated'),
    'suspect_uploads': number(r'Found\s+(\d+)\s+suspect uploads'),
    'dangerous_exposed_files': number(r'Tested\s+(\d+)\s+dangerous files'),
    'public_scripts': number(r'Found\s+(\d+)\s+public scripts'),
    # Trial licenses strip detection details from stdout — keep deep links
    # so the dashboard can surface the full Sansec report + local raw log.
    'report_url': (_report.group(1) if _report else None),
    'raw_log': os.environ.get('RAW_FILE', ''),
}))
" <<< "$RESULT")
    ALL_ACCOUNT_SUMMARIES=$(python3 -c "
import json, sys
a = json.loads(sys.argv[1])
b = json.loads(sys.argv[2])
print(json.dumps(a + [b]))
" "$ALL_ACCOUNT_SUMMARIES" "$ACCT_SUMMARY")

    # Accumulate findings
    ACCT_FINDINGS=$(printf '%s' "$RESULT" | ACCT="$acct" RAW_FILE="$RAW_FILE" python3 -c "
import json, sys
import os
try:
    data = json.load(sys.stdin)
    findings = []
    for r in data.get('check_results', []):
        if r.get('detections'):
            for det in r['detections']:
                # Skip cPanel false positives
                if det.get('name') == 'fake_kernel_thread' and '.panel/cpu/' in det.get('snippet', ''):
                    continue
                findings.append({
                    'account': os.environ['ACCT'],
                    'check': r['name'],
                    'class': det.get('class', ''),
                    'name': det.get('name', ''),
                    'description': det.get('description', ''),
                    'path': det.get('path', ''),
                    'snippet': det.get('snippet', '')[:200],
                    'confidence': det.get('confidence', 0),
                    'moreinfo': det.get('moreinfo', '')
                })
    print(json.dumps(findings))
except Exception as e:
    print('[]')
" 2>/dev/null)

    # Merge
    ALL_FINDINGS=$(echo "$ALL_FINDINGS $ACCT_FINDINGS" | python3 -c "
import json, sys
a = json.loads(sys.argv[1])
b = json.loads(sys.argv[2])
print(json.dumps(a + b))
" "$ALL_FINDINGS" "$ACCT_FINDINGS" 2>/dev/null || echo "$ALL_FINDINGS")

done

# Generate final report
echo "$ALL_FINDINGS" | python3 -c "
import json, sys
findings = json.load(sys.stdin)
summaries = json.loads('''$ALL_ACCOUNT_SUMMARIES''')
malware = sum(s.get('malware', 0) for s in summaries)
vulns = sum(s.get('vulnerabilities', 0) for s in summaries)
critical = malware

report = {
    'scan_time': '$(date -Iseconds)',
    'scanner': 'sansec_ecomscan',
    'accounts': $(printf '%s\n' "${ACCOUNTS[@]}" | python3 -c "import json,sys; print(json.dumps([l.strip() for l in sys.stdin]))"),
    'summary': {
        'total_issues': malware + vulns,
        'critical_confidence': critical,
        'malware': malware,
        'vulnerabilities': vulns
    },
    'account_summaries': summaries,
    'findings': findings
}
print(json.dumps(report, indent=2))
" > "$REPORT_FILE" 2>/dev/null

ln -sf "$REPORT_FILE" "$LATEST_LINK"

echo ""
echo "=== eComscan Complete ==="
echo "Report: $REPORT_FILE"
if [ "$OUTPUT_JSON" = true ]; then
    cat "$REPORT_FILE"
fi
