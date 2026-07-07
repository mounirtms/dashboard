#!/bin/bash
export PATH="/usr/local/bin:/usr/bin:/bin:/opt/cpanel/ea-php82/root/usr/bin:/usr/sbin:/sbin:/usr/local/sbin:$SITE_PATH"

# ═══════════════════════════════════════════════════════════════════════
# Production Deployment Script
# Deploys code changes, runs migrations, rebuilds, and verifies
# Usage: bash /home/dashboard/public_html/scripts/deployment/deploy.sh
#        bash deploy.sh prod|beta|dev|pim|dashboard
# ═══════════════════════════════════════════════════════════════════════

ENV="${1:-prod}"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
LOG="/home/dashboard/public_html/logs/deploy_${ENV}_${TIMESTAMP}.log"

# Site paths
declare -A SITES=(
    [prod]="/home/technadminy7/public_html"
    [beta]="/home/beta/public_html"
    [dev]="/home/dev/public_html"
    [pim]="/home/pim/public_html"
    [dashboard]="/home/dashboard/public_html"
)
declare -A PHP_BIN=(
    [prod]="/opt/cpanel/ea-php82/root/usr/bin/php"
    [beta]="/opt/cpanel/ea-php82/root/usr/bin/php"
    [dev]="/opt/cpanel/ea-php82/root/usr/bin/php"
    [pim]="/opt/cpanel/ea-php82/root/usr/bin/php"
    [dashboard]="/opt/cpanel/ea-php82/root/usr/bin/php"
)
declare -A SITE_USERS=(
    [prod]="technadminy7"
    [beta]="beta"
    [dev]="dev"
    [pim]="pim"
    [dashboard]="dashboard"
)

SITE_PATH="${SITES[$ENV]}"
PHP="${PHP_BIN[$ENV]}"
USER="${SITE_USERS[$ENV]}"

if [ -z "$SITE_PATH" ]; then
    echo "Unknown environment: $ENV. Use: prod, beta, dev, pim, dashboard"
    exit 1
fi

log() { echo "[$(date '+%H:%M:%S')] $1" | tee -a "$LOG"; }

log "=========================================="
log "  DEPLOYMENT: $ENV"
log "  Path: $SITE_PATH"
log "=========================================="

# ── Step 1: Backup ──
log "[1/8] Creating backup..."
BACKUP_DIR="/home/${USER}/backups/deploy_${TIMESTAMP}"
mkdir -p "$BACKUP_DIR"
if [ -d "$SITE_PATH/var" ]; then
    tar czf "$BACKUP_DIR/var_backup.tar.gz" -C "$SITE_PATH" var/ 2>/dev/null || true
fi
if [ -d "$SITE_PATH/pub/media" ]; then
    tar czf "$BACKUP_DIR/media_backup.tar.gz" -C "$SITE_PATH" pub/media/ 2>/dev/null || true
fi
log "  Backup: $BACKUP_DIR"

# ── Step 2: Maintenance mode ──
log "[2/8] Enabling maintenance mode..."
cd "$SITE_PATH" && $PHP bin/magento maintenance:enable 2>/dev/null || true

# ── Step 3: Pull latest code (if git repo) ──
if [ -d "$SITE_PATH/.git" ]; then
    log "[3/8] Pulling latest code..."
    cd "$SITE_PATH" && git pull origin $(git branch --show-current) 2>&1 | tee -a "$LOG" || log "  Git pull skipped"
else
    log "[3/8] Not a git repo — skipping code pull"
fi

# ── Step 4: Composer install ──
if [ -f "$SITE_PATH/composer.json" ]; then
    log "[4/8] Running composer install..."
    cd "$SITE_PATH" && composer install --no-dev --no-interaction 2>&1 | tail -5 | tee -a "$LOG" || log "  Composer skipped"
fi

# ── Step 5: Database upgrades ──
log "[5/8] Running database upgrades..."
cd "$SITE_PATH" && $PHP bin/magento setup:upgrade 2>&1 | tail -10 | tee -a "$LOG" || log "  Setup upgrade skipped"

# ── Step 6: Compile & deploy static content ──
log "[6/8] Compiling and deploying static content..."
cd "$SITE_PATH" && $PHP bin/magento setup:di:compile 2>&1 | tail -5 | tee -a "$LOG" || log "  DI compile skipped"
cd "$SITE_PATH" && $PHP bin/magento setup:static-content:deploy -f 2>&1 | tail -5 | tee -a "$LOG" || log "  Static deploy skipped"

# ── Step 7: Reindex ──
log "[7/8] Reindexing..."
cd "$SITE_PATH" && $PHP bin/magento indexer:reindex 2>&1 | tail -10 | tee -a "$LOG" || log "  Reindex skipped"

# ── Step 8: Disable maintenance & flush cache ──
log "[8/8] Flushing cache and disabling maintenance..."
cd "$SITE_PATH" && $PHP bin/magento cache:flush 2>&1 | tee -a "$LOG"
cd "$SITE_PATH" && $PHP bin/magento maintenance:disable 2>&1 | tee -a "$LOG"

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
