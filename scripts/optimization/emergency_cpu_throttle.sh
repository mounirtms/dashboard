#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════════
# Emergency CPU Throttle - Production Server
# Purpose: Immediately reduce CPU usage in emergency situations
# Location: /home/pim/public_html/emergency_cpu_throttle.sh
# Run: Manually or via monitor when CPU > 90%
# ═══════════════════════════════════════════════════════════════════════════

set -e

LOG_FILE="/home/pim/public_html/var/log/emergency_throttle.log"
mkdir -p "$(dirname "$LOG_FILE")"

log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [EMERGENCY] $1" | tee -a "$LOG_FILE"
}

log_message "=== EMERGENCY CPU THROTTLE INITIATED ==="

# Get current CPU
CPU_LINE=$(top -bn1 | grep "Cpu(s)")
CPU_USER=$(echo "$CPU_LINE" | awk '{print $2}' | cut -d'%' -f1)
CPU_TOTAL=$(echo "$CPU_USER + $(echo "$CPU_LINE" | awk '{print $4}' | cut -d'%' -f1)" | bc)

log_message "Current CPU: ${CPU_TOTAL}%"

# ═══════════════════════════════════════════════════════════════════════════
# Level 1: Kill highest CPU PHP-FPM processes (non-master)
# ═══════════════════════════════════════════════════════════════════════════

log_message "Level 1: Killing highest CPU PHP-FPM workers..."

ps aux --sort=-%cpu | grep -E "php-fpm.*technostationery|php-fpm.*pim" | grep -v master | head -5 | while read line; do
    PID=$(echo $line | awk '{print $2}')
    CPU=$(echo $line | awk '{print $3}')
    
    if (( $(echo "$CPU > 30" | bc -l 2>/dev/null || echo 0) )); then
        log_message "  Killing PID $PID (CPU: ${CPU}%)"
        kill $PID 2>/dev/null || true
    fi
done

sleep 2

# ═══════════════════════════════════════════════════════════════════════════
# Level 2: Kill Magento queue consumers
# ═══════════════════════════════════════════════════════════════════════════

log_message "Level 2: Stopping Magento queue consumers..."

pkill -f "queue:consumers:start" 2>/dev/null || true
log_message "  All queue consumers stopped"

sleep 2

# ═══════════════════════════════════════════════════════════════════════════
# Level 3: Clear Magento cache
# ═══════════════════════════════════════════════════════════════════════════

log_message "Level 3: Clearing Magento cache..."

cd /home/technadminy7/public_html
rm -rf var/cache/* var/page_cache/* var/generation/* 2>/dev/null || true
log_message "  Magento cache cleared"

# ═══════════════════════════════════════════════════════════════════════════
# Level 4: Clear PIM cache
# ═══════════════════════════════════════════════════════════════════════════

log_message "Level 4: Clearing PIM cache..."

cd /home/pim/public_html
rm -rf var/cache/prod/* 2>/dev/null || true
log_message "  PIM cache cleared"

# ═══════════════════════════════════════════════════════════════════════════
# Level 5: Optimize MariaDB
# ═══════════════════════════════════════════════════════════════════════════

log_message "Level 5: Optimizing MariaDB..."

/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 -e "FLUSH TABLES; FLUSH STATUS;" 2>/dev/null || true
log_message "  MariaDB optimized"

# ═══════════════════════════════════════════════════════════════════════════
# Final Status
# ═══════════════════════════════════════════════════════════════════════════

sleep 5

CPU_AFTER=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d'%' -f1)
log_message "CPU after throttle: ${CPU_AFTER}%"

if (( $(echo "$CPU_AFTER > 80" | bc -l 2>/dev/null || echo 0) )); then
    log_message "WARNING: CPU still high after emergency throttle!"
    log_message "Consider restarting services or investigating root cause"
else
    log_message "SUCCESS: CPU reduced to ${CPU_AFTER}%"
fi

log_message "=== EMERGENCY THROTTLE COMPLETED ==="
log_message ""

exit 0
