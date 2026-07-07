#!/bin/bash
# ============================================
# Post-Deployment Health Check Script
# ============================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

declare -A ENV_DOMAINS=(
  ["dev"]="dev.technostationery.com"
  ["beta"]="beta.technostationery.com"
  ["prod"]="technostationery.com"
  ["dashboard"]="dashboard.technostationery.com"
)

declare -A ENV_PATHS=(
  ["dev"]="/home/dev/public_html"
  ["beta"]="/home/beta/public_html"
  ["prod"]="/home/technadminy7/public_html"
  ["dashboard"]="/home/dashboard/public_html"
)

log() { echo -e "${BLUE}[$(date +'%H:%M:%S')]${NC} $1"; }
pass() { echo -e "  ${GREEN}✓${NC} $1"; }
fail() { echo -e "  ${RED}✗${NC} $1"; }
warn() { echo -e "  ${YELLOW}⚠${NC} $1"; }

check_http() {
    local env=$1
    local domain=${ENV_DOMAINS[$env]}
    local code=$(curl -sS -o /dev/null -w "%{http_code}" --max-time 10 "https://${domain}/" 2>/dev/null || echo "000")
    
    if [ "$code" != "000" ]; then
        pass "HTTP ${code} - ${domain}"
        return 0
    else
        fail "HTTP ${code} - ${domain}"
        return 1
    fi
}

check_php() {
    local env=$1
    local path=${ENV_PATHS[$env]}
    
    # Check PHP-FPM processes for this user
    local user=$(basename "$path" | cut -d/ -f3)
    local count=$(ps aux | grep "php-fpm.*${user}" | grep -v grep | wc -l)
    
    if [ "$count" -gt 0 ]; then
        pass "PHP-FPM: ${count} workers for ${user}"
        return 0
    else
        fail "PHP-FPM: No workers for ${user}"
        return 1
    fi
}

check_disk() {
    local path=${ENV_PATHS[$env]}
    local pct=$(df "$path" 2>/dev/null | tail -1 | awk '{print $5}' | tr -d '%')
    
    if [ -n "$pct" ]; then
        if [ "$pct" -lt 80 ]; then
            pass "Disk: ${pct}% used"
            return 0
        elif [ "$pct" -lt 90 ]; then
            warn "Disk: ${pct}% used (getting high)"
            return 0
        else
            fail "Disk: ${pct}% used (CRITICAL)"
            return 1
        fi
    else
        fail "Disk: Unable to check"
        return 1
    fi
}

check_services() {
    local services=("ea-php82-php-fpm" "httpd" "mariadb10.6" "redis" "crond")
    local all_ok=true
    
    for svc in "${services[@]}"; do
        local status=$(systemctl is-active "$svc" 2>/dev/null || echo "unknown")
        if [ "$status" = "active" ]; then
            pass "Service: ${svc} running"
        else
            fail "Service: ${svc} - ${status}"
            all_ok=false
        fi
    done
    
    $all_ok && return 0 || return 1
}

check_magento() {
    local env=$1
    local path=${ENV_PATHS[$env]}
    
    if [ ! -f "${path}/bin/magento" ]; then
        warn "Not a Magento site"
        return 0
    fi
    
    # Check if var/cache exists and is not too large
    if [ -d "${path}/var/cache" ]; then
        local cache_size=$(du -sm "${path}/var/cache" 2>/dev/null | awk '{print $1}')
        if [ -n "$cache_size" ] && [ "$cache_size" -lt 500 ]; then
            pass "Magento cache: ${cache_size}MB"
        else
            warn "Magento cache: ${cache_size}MB (consider flushing)"
        fi
    fi
    
    # Check maintenance mode
    if [ -f "${path}/var/.maintenance.flag" ]; then
        warn "Maintenance mode is ENABLED"
    else
        pass "Maintenance mode: OFF"
    fi
}

check_error_log() {
    local env=$1
    local path=${ENV_PATHS[$env]}
    local log_file="${path}/error_log"
    local api_log="${path}/api/error_log"
    
    # Check recent errors (last 5 minutes)
    if [ -f "$log_file" ]; then
        local recent=$(find "$log_file" -mmin -5 2>/dev/null | wc -l)
        if [ "$recent" -gt 0 ]; then
            local count=$(tail -50 "$log_file" | grep -c "Fatal\|Error" || true)
            if [ "$count" -gt 0 ]; then
                warn "PHP errors: ${count} in recent log"
            else
                pass "PHP error log: No recent errors"
            fi
        else
            pass "PHP error log: No recent changes"
        fi
    fi
}

check_queue_status() {
    if command -v php &>/dev/null; then
        local prod_path="/home/technadminy7/public_html"
        if [ -f "${prod_path}/bin/magento" ]; then
            local queue_count=$(cd "$prod_path" && php bin/magento queue:consumers:list 2>/dev/null | wc -l || echo "0")
            if [ "$queue_count" -gt 0 ]; then
                pass "Queue consumers: ${queue_count} listed"
            else
                warn "Queue consumers: Unable to list"
            fi
        fi
    fi
}

# ── Main ──
ENV=${1:-"all"}
echo ""
echo "========================================="
echo "  Health Check: ${ENV^^}"
echo "  $(date)"
echo "========================================="
echo ""

errors=0

if [ "$ENV" = "all" ]; then
    environments=("dev" "beta" "prod" "dashboard")
else
    environments=("$ENV")
fi

for env in "${environments[@]}"; do
    if [ -z "${ENV_DOMAINS[$env]+x}" ]; then
        fail "Unknown environment: $env"
        ((errors++))
        continue
    fi

    echo -e "\n${BLUE}━━━ ${env^^} (${ENV_DOMAINS[$env]}) ━━━${NC}"
    
    check_http "$env" || ((errors++))
    check_php "$env" || ((errors++))
    check_disk "$env" || ((errors++))
    check_magento "$env"
    check_error_log "$env"
done

echo ""
echo -e "\n${BLUE}━━━ Global Services ━━━${NC}"
check_services || ((errors++))
check_queue_status

echo ""
echo "========================================="
if [ "$errors" -eq 0 ]; then
    echo -e "  ${GREEN}✓ All checks passed!${NC}"
else
    echo -e "  ${RED}✗ ${errors} check(s) failed${NC}"
fi
echo "========================================="
echo ""

exit $errors
