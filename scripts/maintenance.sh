#!/bin/bash
# Akeneo PIM & Dashboard Maintenance Script
# Run this regularly to maintain system health

set -e
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_FILE="/home/dashboard/public_html/maintenance.log"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

log "=== Starting System Maintenance ==="

# 1. Check Dashboard
log "Checking Dashboard..."
if curl -sf https://dashboard.technostationery.com > /dev/null 2>&1; then
    log "Dashboard: OK"
else
    log "Dashboard: FAILED - check immediately!"
fi

# 2. Check Akeneo PIM
log "Checking Akeneo PIM..."
if curl -sf https://pim.technostationery.com/user/login > /dev/null 2>&1; then
    log "Akeneo PIM: OK"
else
    log "Akeneo PIM: FAILED - check immediately!"
fi

# 3. Check Database
log "Checking MySQL..."
if mysql --socket=/opt/mariadb10.6/mariadb.sock -u root -p'YourNewStrongPassword' --skip-ssl -e "SELECT 1" > /dev/null 2>&1; then
    log "MySQL: OK"
else
    log "MySQL: FAILED!"
fi

# 4. Check Elasticsearch
log "Checking Elasticsearch..."
if curl -sf localhost:9200/_cluster/health > /dev/null 2>&1; then
    log "Elasticsearch: OK"
else
    log "Elasticsearch: FAILED!"
fi

# 5. Clear Dashboard Cache (if needed)
log "Clearing Dashboard Cache..."
php /home/dashboard/public_html/bin/console cache:clear --env=prod 2>/dev/null || true

# 6. Clear PIM Cache (if needed)
log "Clearing PIM Cache..."
php /home/pim/akeneopublic_html/bin/console cache:clear --env=prod 2>/dev/null || true

# 7. Check Disk Space
log "Disk Usage:"
df -h /home | tail -1 | tee -a "$LOG_FILE"

log "=== Maintenance Complete ==="
echo ""
echo "Log saved to: $LOG_FILE"