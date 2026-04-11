#!/bin/bash
# ============================================
# Rollback Script - Revert last deployment
# ============================================

set -e

TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
LOG_DIR="/home/dashboard/public_html/logs/deployments"
BACKUP_DIR="/home/dashboard/public_html/backups"

declare -A ENV_PATHS=(
  ["dev"]="/home/dev/public_html"
  ["beta"]="/home/beta/public_html"
  ["prod"]="/home/technadminy7/public_html"
  ["dashboard"]="/home/dashboard/public_html"
)

declare -A ENV_USERS=(
  ["dev"]="dev"
  ["beta"]="beta"
  ["prod"]="technadminy7"
  ["dashboard"]="dashboard"
)

declare -A ENV_DOMAINS=(
  ["dev"]="dev.technostationery.com"
  ["beta"]="beta.technostationery.com"
  ["prod"]="technostationery.com"
  ["dashboard"]="dashboard.technostationery.com"
)

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log() { echo -e "${BLUE}[$(date +'%H:%M:%S')]${NC} $1"; }
success() { echo -e "${GREEN}✓${NC} $1"; }
warn() { echo -e "${YELLOW}⚠${NC} $1"; }
error() { echo -e "${RED}✗${NC} $1"; }

ENV=$1

if [ -z "$ENV" ]; then
    error "Usage: $0 <environment> [--list]"
    echo ""
    echo "Environments: dev, beta, prod, dashboard"
    echo "Options:"
    echo "  --list    List available backups"
    exit 1
fi

if [ "$ENV" = "--list" ]; then
    echo "Available backups:"
    ls -lh "${BACKUP_DIR}/"*.tar.gz 2>/dev/null || echo "No backups found"
    exit 0
fi

if [ -z "${ENV_PATHS[$ENV]+x}" ]; then
    error "Unknown environment: $ENV"
    echo "Available: dev, beta, prod, dashboard"
    exit 1
fi

# Find latest backup for this environment
LATEST_BACKUP=$(ls -t "${BACKUP_DIR}/${ENV}_backup_"*.tar.gz 2>/dev/null | head -1)

if [ -z "$LATEST_BACKUP" ]; then
    error "No backup found for ${ENV}"
    echo "Available backups:"
    ls -lh "${BACKUP_DIR}/" 2>/dev/null || echo "None"
    exit 1
fi

# Confirmation for production
if [ "$ENV" = "prod" ]; then
    warn "⚠️  ROLLING BACK PRODUCTION!"
    echo "Backup: ${LATEST_BACKUP}"
    echo ""
    read -p "Type 'ROLLBACK PROD' to confirm: " confirm
    if [ "$confirm" != "ROLLBACK PROD" ]; then
        error "Rollback cancelled"
        exit 1
    fi
fi

log "============================================"
log "  ROLLBACK: ${ENV^^}"
log "  Backup: ${LATEST_BACKUP}"
log "============================================"

# Create a pre-rollback backup
log "Creating pre-rollback backup..."
PRE_ROLLBACK="${BACKUP_DIR}/${ENV}_pre_rollback_${TIMESTAMP}.tar.gz"
if tar -czf "$PRE_ROLLBACK" -C "$(dirname "${ENV_PATHS[$ENV]}")" "$(basename "${ENV_PATHS[$ENV]}")" 2>/dev/null; then
    success "Pre-rollback backup: $PRE_ROLLBACK"
else
    warn "Pre-rollback backup failed (continuing anyway)"
fi

# Extract backup
log "Restoring from backup..."
if tar -xzf "$LATEST_BACKUP" -C "/" 2>&1; then
    success "Files restored from ${LATEST_BACKUP}"
else
    error "Restore failed! Attempting to re-apply pre-rollback backup..."
    if [ -f "$PRE_ROLLBACK" ]; then
        tar -xzf "$PRE_ROLLBACK" -C "/" 2>/dev/null
        error "Reverted to pre-rollback state"
    fi
    exit 1
fi

# Fix permissions
USER=${ENV_USERS[$ENV]}
log "Fixing permissions..."
chown -R "${USER}:${USER}" "${ENV_PATHS[$ENV]}/" 2>/dev/null || true
find "${ENV_PATHS[$ENV]}/" -type d -exec chmod 755 {} \; 2>/dev/null || true
find "${ENV_PATHS[$ENV]}/" -type f -exec chmod 644 {} \; 2>/dev/null || true
success "Permissions fixed"

# Restart PHP-FPM
log "Restarting PHP-FPM..."
if systemctl restart ea-php82-php-fpm 2>&1; then
    success "PHP-FPM restarted"
fi

# Health check
sleep 2
DOMAIN=${ENV_DOMAINS[$ENV]}
HTTP_CODE=$(curl -sS -o /dev/null -w "%{http_code}" --max-time 10 "https://${DOMAIN}/" 2>/dev/null || echo "000")

echo ""
log "============================================"
if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "301" ] || [ "$HTTP_CODE" = "302" ]; then
    success "🔄 Rollback complete! ${DOMAIN} responding (HTTP ${HTTP_CODE})"
else
    warn "Site responding with HTTP ${HTTP_CODE} - verify manually"
fi
log "============================================"
echo ""

# Log the rollback
mkdir -p "$LOG_DIR"
echo "[$(date)] Rollback ${ENV} from ${LATEST_BACKUP} (HTTP ${HTTP_CODE})" >> "${LOG_DIR}/rollback_log.txt"

exit 0
