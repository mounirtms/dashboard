#!/bin/bash
export PATH="/usr/local/bin:/usr/bin:/bin:/opt/cpanel/ea-php82/root/usr/bin:/usr/sbin:/sbin:/usr/local/sbin:$SITE_PATH"

# ═══════════════════════════════════════════════════════════════════════
# Multi-Environment Deployment Script
# Deploys code changes, runs migrations, rebuilds, and verifies
# Usage: bash deploy.sh <env> [options]
#   env: prod|dev|tsdnd|pim|dashboard   (beta = deprecated alias of tsdnd)
#   options: --quick  (skip heavy steps: composer, DI compile details)
#            flush    (only flush cache, no deploy)
#            build    (build only, no deploy to other envs)
# ═══════════════════════════════════════════════════════════════════════

set -e

ENV="${1:-prod}"
shift || true

# ── Memory-safe settings ───────────────────────────────────────────────
# The "Application code generator" (setup:di:compile) and Composer can
# fail with mmap() errors on memory-constrained CI runners. On the server
# (31GB RAM) these are not an issue, but we set sane defaults anyway.
export MALLOC_ARENA_MAX="${MALLOC_ARENA_MAX:-2}"
export COMPOSER_MEMORY_LIMIT="${COMPOSER_MEMORY_LIMIT:--1}"
export PHP_CLI_MEMORY_LIMIT="${PHP_CLI_MEMORY_LIMIT:-4G}"
export MAGENTO_CUSTOM_MEMORY_LIMIT="${MAGENTO_CUSTOM_MEMORY_LIMIT:-2G}"

# Site paths (declared before PHP assignment below)
declare -A SITES=(
    [prod]="/home/technadminy7/public_html"
    [tsdnd]="/home/tsdnd/public_html"
    # DEPRECATED: /home/beta was removed — beta now maps to tsdnd (staging mirror)
    [beta]="/home/tsdnd/public_html"
    [dev]="/home/dev/public_html"
    [pim]="/home/pim/public_html"
    [dashboard]="/home/dashboard/public_html"
)
declare -A PHP_BIN=(
    [prod]="/opt/cpanel/ea-php82/root/usr/bin/php"
    [tsdnd]="/opt/cpanel/ea-php82/root/usr/bin/php"
    [beta]="/opt/cpanel/ea-php82/root/usr/bin/php"
    [dev]="/opt/cpanel/ea-php82/root/usr/bin/php"
    [pim]="/opt/cpanel/ea-php82/root/usr/bin/php"
    [dashboard]="/opt/cpanel/ea-php82/root/usr/bin/php"
)
declare -A SITE_USERS=(
    [prod]="technadminy7"
    [tsdnd]="tsdnd"
    [beta]="tsdnd"
    [dev]="dev"
    [pim]="pim"
    [dashboard]="dashboard"
)

PHP="${PHP_BIN[$ENV]:-/opt/cpanel/ea-php82/root/usr/bin/php}"
PHP_BUILD_CMD="$PHP -d memory_limit=$MAGENTO_CUSTOM_MEMORY_LIMIT"

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
LOG="/home/dashboard/public_html/logs/deploy_${ENV}_${TIMESTAMP}.log"

SITE_PATH="${SITES[$ENV]}"
USER="${SITE_USERS[$ENV]}"

# ── Parse options ─────────────────────────────────────────────────────
QUICK=false
FLUSH_ONLY=false
BUILD_ONLY=false
DRY_RUN=false
ARGS=("$@")

for arg in "${ARGS[@]}"; do
    case "$arg" in
        --quick)         QUICK=true ;;
        --flush|flush)   FLUSH_ONLY=true ;;
        --build|build)   BUILD_ONLY=true ;;
        --dry-run|--dry) DRY_RUN=true ;;
    esac
done

if [ -z "$SITE_PATH" ]; then
    echo "Unknown environment: $ENV. Use: prod, tsdnd, dev, pim, dashboard (beta=deprecated alias of tsdnd)"
    exit 1
fi

# Release-based layout standard: $SITE_PATH/current -> releases/<ts>/.
# Every bin/magento + composer invocation must run from the ACTIVE release;
# the root-level bin/ dirs left behind by the pre-rebuild flat installs on
# tsdnd/production are stale and must never execute. dev has no root bin/ at all.
MROOT="${SITE_PATH}/current"
if [ ! -f "$MROOT/bin/magento" ]; then
    MROOT="$SITE_PATH"   # legacy flat install fallback
fi

log() { echo "[$(date '+%H:%M:%S')] $1" | tee -a "$LOG"; }

log "=========================================="
log "  DEPLOYMENT: $ENV to $SITE_PATH"
[ "$QUICK" = true ] && log "  Mode: quick (lightweight)"
[ "$FLUSH_ONLY" = true ] && log "  Mode: flush only"
[ "$BUILD_ONLY" = true ] && log "  Mode: build only"
[ "$DRY_RUN" = true ] && log "  Mode: DRY RUN (no actual changes)"
log "=========================================="

# ── Dry run: just validate and exit ─────────────────────────────────────
if [ "$DRY_RUN" = true ]; then
    log "[DRY-RUN] Environment: $ENV"
    log "[DRY-RUN] Site path: $SITE_PATH"
    log "[DRY-RUN] PHP binary: $PHP"
    log "[DRY-RUN] Would run deployment steps here"
    log "[DRY-RUN] Deployment steps that would be executed:"
    if [ "$QUICK" = true ]; then
        log "  - [SKIPPED] Backup (quick mode)"
        log "  - [SKIPPED] Git pull (quick mode)"
        log "  - [SKIPPED] Composer install (quick mode)"
    else
        log "  - Backup var/ and pub/media/"
        log "  - Enable maintenance mode"
        log "  - Git pull origin"
        log "  - Composer install --no-dev"
        log "  - bin/magento setup:upgrade"
    fi
    if [ "$QUICK" = false ]; then
        log "  - bin/magento setup:di:compile (MALLOC_ARENA_MAX=2, SCOUT_DISABLE=1)"
    fi
    log "  - bin/magento setup:static-content:deploy -f (MALLOC_ARENA_MAX=2, SCOUT_DISABLE=1)"
    if [ "$BUILD_ONLY" = true ]; then
        log "  - [SKIPPED] Git pull, composer, setup:upgrade, reindex (build only)"
    fi
    log "  - bin/magento cache:flush"
    log "  - Disable maintenance mode"
    log "  - Fix permissions (chown/chmod)"
    log "[DRY-RUN] Skipping actual deployment."
    log "=========================================="
    log "  DRY RUN COMPLETE: $ENV"
    log "  No changes were made."
    log "=========================================="
exit 0
fi

# ── Handle special modes ──────────────────────────────────────────────────────────────────
if [ "$FLUSH_ONLY" = true ]; then
    log "[FLUSH] Flushing cache only (no deployment steps)..."
    cd "$MROOT" && $PHP_BUILD_CMD bin/magento cache:flush 2>&1 | tee -a "$LOG"
    cd "$MROOT" && $PHP bin/magento maintenance:disable 2>&1 | tee -a "$LOG" || true
    log "=========================================="
    log "  CACHE FLUSHED: $ENV"
    log "=========================================="
    exit 0
fi

# ── Step 1: Backup ──
log "[1/8] Creating backup..."
if [ "$QUICK" = true ] || [ "$BUILD_ONLY" = true ]; then
    log "  Skipped (quick/build mode)"
else
    BACKUP_DIR="/home/${USER}/backups/deploy_${TIMESTAMP}"
    mkdir -p "$BACKUP_DIR"
    if [ -d "$SITE_PATH/var" ]; then
        tar czf "$BACKUP_DIR/var_backup.tar.gz" -C "$SITE_PATH" var/ 2>/dev/null || true
    fi
    if [ -d "$SITE_PATH/pub/media" ]; then
        tar czf "$BACKUP_DIR/media_backup.tar.gz" -C "$SITE_PATH" pub/media/ 2>/dev/null || true
    fi
    log "  Backup: $BACKUP_DIR"
fi

# ── Step 2: Maintenance mode ──
log "[2/8] Enabling maintenance mode..."
cd "$MROOT" && $PHP bin/magento maintenance:enable 2>/dev/null || log "  Maintenance mode skipped (may already be enabled)"

# ── Step 3: Pull latest code (if git repo) ──
if [ "$BUILD_ONLY" = true ]; then
    log "[3/8] Skipped (build only mode)"
elif [ -d "$MROOT/.git" ]; then
    log "[3/8] Pulling latest code..."
    cd "$MROOT" && git pull origin $(git branch --show-current) 2>&1 | tee -a "$LOG" || log "  Git pull skipped"
else
    log "[3/8] Not a git repo — skipping code pull"
fi

# ── Step 4: Composer install (conditional) ──
if [ "$BUILD_ONLY" = true ] || [ "$QUICK" = true ]; then
    log "[4/8] Skipped (quick/build mode)"
elif [ -f "$MROOT/composer.json" ]; then
    LOCK_HASH=$(md5sum "$MROOT/composer.lock" "$MROOT/composer.json" 2>/dev/null | md5sum | awk '{print $1}')
    if [ -d "$MROOT/vendor" ] && [ -f "$MROOT/vendor/.composer_lock_hash" ] && [ "$(cat "$MROOT/vendor/.composer_lock_hash" 2>/dev/null)" = "$LOCK_HASH" ] && [ -f "$MROOT/vendor/autoload.php" ]; then
        log "[4/8] Composer lock unchanged ($LOCK_HASH) — skipping composer install"
    else
        log "[4/8] Dependencies changed or vendor missing — Running composer install..."
        cd "$MROOT" && $PHP_BUILD_CMD -d memory_limit=-1 composer install --no-dev --no-interaction 2>&1 | tail -5 | tee -a "$LOG" || log "  Composer skipped"
        echo "$LOCK_HASH" > "$MROOT/vendor/.composer_lock_hash" 2>/dev/null || true
    fi
fi

# ── Step 5: Database upgrades ──
if [ "$BUILD_ONLY" = true ] || [ "$QUICK" = true ]; then
    log "[5/8] Skipped (quick/build mode)"
else
    log "[5/8] Running database upgrades..."
    cd "$MROOT" && $PHP_BUILD_CMD bin/magento setup:upgrade 2>&1 | tail -10 | tee -a "$LOG" || log "  Setup upgrade skipped"
fi

# ── Step 6: Compile & deploy static content ──
if [ "$BUILD_ONLY" = true ]; then
    log "[6/8] Building (compile + static deploy)..."
elif [ "$QUICK" = true ]; then
    log "[6/8] Skipped (quick mode)"
else
    log "[6/8] Compiling and deploying static content..."
fi
# Always attempt DI compile (memory-safe) — this is the "Application code generator"
if [ "$QUICK" = false ]; then
    cd "$MROOT" && export MALLOC_ARENA_MAX=2 && $PHP_BUILD_CMD bin/magento setup:di:compile 2>&1 | tail -5 | tee -a "$LOG" || log "  DI compile failed (check memory)"
fi
# Static content deployment — use SCOUT_DISABLE=1 to reduce memory for product/category pages
cd "$MROOT" && export MALLOC_ARENA_MAX=2 && export SCOUT_DISABLE=1 && $PHP_BUILD_CMD bin/magento setup:static-content:deploy -f 2>&1 | tail -5 | tee -a "$LOG" || log "  Static deploy skipped"

# ── Step 7: Reindex ──
if [ "$BUILD_ONLY" = true ] || [ "$QUICK" = true ]; then
    log "[7/8] Skipped (quick/build mode)"
else
    log "[7/8] Reindexing..."
    cd "$MROOT" && $PHP_BUILD_CMD bin/magento indexer:reindex 2>&1 | tail -10 | tee -a "$LOG" || log "  Reindex skipped"
fi

# ── Step 8: Disable maintenance & flush cache ──
log "[8/8] Flushing cache and disabling maintenance..."
cd "$MROOT" && $PHP bin/magento cache:flush 2>&1 | tee -a "$LOG"
cd "$MROOT" && $PHP bin/magento maintenance:disable 2>&1 | tee -a "$LOG" || true

# ── Fix permissions ──
log "Fixing permissions..."
chown -R "${USER}:${USER}" "$SITE_PATH" 2>/dev/null || true
chmod -R 755 "$SITE_PATH/pub/static" 2>/dev/null || true
chmod -R 755 "$SITE_PATH/var" 2>/dev/null || true
chmod -R 755 "$SITE_PATH/generated" 2>/dev/null || true

# ── Final status ──
log "=========================================="
log "  DEPLOYMENT COMPLETE: $ENV"
log "  Load: $(uptime)"
log "=========================================="
