#!/bin/bash
###############################################################################
# Session Audit Script - PRODUCTION READY
# Purpose: Audit session configuration and diagnose session issues
# Usage: ./session_audit.sh [--fix]
# Safety: Read-only by default, non-destructive
###############################################################################

set +e

# Configuration
MAGENTO_ROOT="/home/technadminy7/public_html"
REDIS_HOST="127.0.0.1"
REDIS_PORT="6379"
LOG_FILE="${MAGENTO_ROOT}/var/log/session_audit.log"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

FIX_MODE=false
if [ "$1" == "--fix" ]; then
    FIX_MODE=true
    echo "[FIX MODE - Will apply safe fixes]"
fi

log_info() { echo -e "${GREEN}[INFO]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1" | tee -a "$LOG_FILE"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1" | tee -a "$LOG_FILE"; }
log_error() { echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1" | tee -a "$LOG_FILE"; }

echo "========================================="
echo "Session Audit Report"
echo "Started: $(date '+%Y-%m-%d %H:%M:%S')"
echo "========================================="
echo ""

# Step 1: Check session configuration
log_info "Step 1: Checking session configuration..."
if [ -f "${MAGENTO_ROOT}/app/etc/env.php" ]; then
    SESSION_SAVE=$(grep -A 3 "'session'" "${MAGENTO_ROOT}/app/etc/env.php" | grep "'save'" | grep -oP "'save' => '\K[^']+")
    log_info "Session save handler: ${SESSION_SAVE:-'not configured'}"
    
    if [ "$SESSION_SAVE" = "redis" ]; then
        log_info "✓ Redis session handler configured"
    else
        log_warn "Session handler is not Redis (current: ${SESSION_SAVE:-'unknown'})"
    fi
else
    log_error "env.php not found"
fi
echo ""

# Step 2: Check Redis session database
log_info "Step 2: Checking Redis session database..."
if command -v redis-cli &> /dev/null; then
    REDIS_DB_SIZE=$(redis-cli -h ${REDIS_HOST} -p ${REDIS_PORT} -n 2 DBSIZE 2>/dev/null | awk '{print $2}')
    log_info "Redis DB2 (sessions) keys: ${REDIS_DB_SIZE:-0}"
    
    if [ "${REDIS_DB_SIZE:-0}" -gt 0 ]; then
        log_info "✓ Sessions exist in Redis"
    else
        log_warn "No sessions found in Redis DB2"
    fi
    
    # Check session TTL
    SESSION_TTL=$(redis-cli -h ${REDIS_HOST} -p ${REDIS_PORT} -n 2 CONFIG GET timeout 2>/dev/null | tail -1)
    log_info "Redis session timeout: ${SESSION_TTL:-default} seconds"
else
    log_error "redis-cli not available"
fi
echo ""

# Step 3: Check session directory
log_info "Step 3: Checking session directory..."
SESSION_DIR="${MAGENTO_ROOT}/var/session"
if [ -d "$SESSION_DIR" ]; then
    SESSION_COUNT=$(find "$SESSION_DIR" -type f 2>/dev/null | wc -l)
    log_info "Session directory: $SESSION_DIR"
    log_info "Session files: $SESSION_COUNT"
else
    log_info "Session directory does not exist (using Redis?)"
fi
echo ""

# Step 4: Check log permissions
log_info "Step 4: Checking log file permissions..."
PERM_ISSUES=0

for log_file in "${MAGENTO_ROOT}/var/log"/*.log; do
    if [ -f "$log_file" ]; then
        OWNER=$(stat -c '%U' "$log_file" 2>/dev/null)
        PERMS=$(stat -c '%a' "$log_file" 2>/dev/null)
        
        if [ "$OWNER" != "technadminy7" ]; then
            log_warn "$log_file owned by $OWNER (expected: technadminy7)"
            PERM_ISSUES=$((PERM_ISSUES + 1))
            
            if [ "$FIX_MODE" = true ]; then
                chown technadminy7:technadminy7 "$log_file" 2>/dev/null && log_info "Fixed: $log_file"
            fi
        fi
        
        if [ "$PERMS" != "644" ]; then
            log_warn "$log_file permissions: $PERMS (expected: 644)"
            PERM_ISSUES=$((PERM_ISSUES + 1))
            
            if [ "$FIX_MODE" = true ]; then
                chmod 644 "$log_file" 2>/dev/null && log_info "Fixed: $log_file"
            fi
        fi
    fi
done

if [ $PERM_ISSUES -eq 0 ]; then
    log_info "✓ All log permissions OK"
else
    log_warn "Found $PERM_ISSUES permission issue(s)"
fi
echo ""

# Step 5: Check admin users
log_info "Step 5: Checking admin users..."
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e "
    SELECT username, email, is_active, interface_locale, created 
    FROM admin_user 
    WHERE is_active = 1 
    ORDER BY created DESC 
    LIMIT 10;
" 2>&1 | tee -a "$LOG_FILE"
echo ""

# Step 6: Check for session errors in logs
log_info "Step 6: Checking for session errors in logs..."
SESSION_ERRORS=$(grep -i "session" "${MAGENTO_ROOT}/var/log/exception.log" 2>/dev/null | tail -20 | wc -l)
log_info "Session-related exceptions: $SESSION_ERRORS"

if [ "$SESSION_ERRORS" -gt 0 ]; then
    log_warn "Recent session errors found:"
    grep -i "session" "${MAGENTO_ROOT}/var/log/exception.log" 2>/dev/null | tail -5 | tee -a "$LOG_FILE"
fi
echo ""

# Step 7: Check cookie configuration
log_info "Step 7: Checking cookie configuration..."
COOKIE_DOMAIN=$(grep -A 3 "'cookie'" "${MAGENTO_ROOT}/app/etc/env.php" 2>/dev/null | grep "cookie_domain" | grep -oP "'cookie_domain' => '\K[^']+")
COOKIE_PATH=$(grep -A 3 "'cookie'" "${MAGENTO_ROOT}/app/etc/env.php" 2>/dev/null | grep "cookie_path" | grep -oP "'cookie_path' => '\K[^']+")

log_info "Cookie domain: ${COOKIE_DOMAIN:-'not set'}"
log_info "Cookie path: ${COOKIE_PATH:-'/'}"
echo ""

# Step 8: Redis memory check
log_info "Step 8: Checking Redis memory..."
REDIS_USED=$(redis-cli -h ${REDIS_HOST} -p ${REDIS_PORT} INFO memory 2>/dev/null | grep "used_memory_human" | cut -d: -f2 | tr -d '\r')
REDIS_MAX=$(redis-cli -h ${REDIS_HOST} -p ${REDIS_PORT} CONFIG GET maxmemory 2>/dev/null | tail -1)
REDIS_MAX_HUMAN=$(echo "$REDIS_MAX" | awk '{if ($1 > 1073741824) printf "%.1fGB", $1/1073741824; else printf "%dMB", $1/1048576;}')

log_info "Redis memory: ${REDIS_USED:-unknown} / ${REDIS_MAX_HUMAN:-unknown}"
echo ""

# Summary
log_info "========================================="
log_info "Session Audit Summary"
log_info "========================================="
log_info "Session handler: ${SESSION_SAVE:-unknown}"
log_info "Redis sessions: ${REDIS_DB_SIZE:-0}"
log_info "Log permission issues: $PERM_ISSUES"
log_info "Session errors: $SESSION_ERRORS"
log_info "========================================="

if [ "$FIX_MODE" = false ]; then
    log_info "Run with --fix to apply safe fixes"
fi

echo ""
echo "========================================="

exit 0
