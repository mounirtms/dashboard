#!/bin/bash
# ──────────────────────────────────────────────────────────────────────
# Magento Cron Schedule Cleanup
# Purpose: Keep cron_schedule table clean to prevent database bloat
# Run this daily at 3:00 AM (before master_cleanup)
# ──────────────────────────────────────────────────────────────────────

LOGFILE="/home/technadminy7/public_html/var/log/cron_cleanup.log"
Mysql="mysql -h 127.0.0.1 -P 3307 -u technadminy7_ntdbusr24 -p'the-correct-password' --skip-ssl technadminy7_dBT8x12y22"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOGFILE"
}

log "=== Cron Cleanup Starting ==="

# Count current state
BEFORE=$($Mysql -N -e "SELECT COUNT(*) FROM cron_schedule;" 2>/dev/null)
log "Before cleanup: $BEFORE rows"

# Clean missed jobs older than 1 hour
$Mysql -e "DELETE FROM cron_schedule WHERE status = 'missed' AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR);" 2>/dev/null
MISSED=$($Mysql -N -e "SELECT ROW_COUNT();" 2>/dev/null)
log "Deleted $MISSED missed jobs"

# Clean success jobs older than 24 hours
$Mysql -e "DELETE FROM cron_schedule WHERE status = 'success' AND finished_at < DATE_SUB(NOW(), INTERVAL 24 HOUR);" 2>/dev/null
SUCCESS=$($Mysql -N -e "SELECT ROW_COUNT();" 2>/dev/null)
log "Deleted $SUCCESS old success jobs"

# Clean error jobs older than 7 days
$Mysql -e "DELETE FROM cron_schedule WHERE status = 'error' AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);" 2>/dev/null
ERRORS=$($Mysql -N -e "SELECT ROW_COUNT();" 2>/dev/null)
log "Deleted $ERRORS old error jobs"

# Count after cleanup
AFTER=$($Mysql -N -e "SELECT COUNT(*) FROM cron_schedule;" 2>/dev/null)
log "After cleanup: $AFTER rows"
log "=== Cron Cleanup Done ==="
