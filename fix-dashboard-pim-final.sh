#!/bin/bash
# Final fix for Dashboard and PIM - disable all proxies

LOG_FILE="/home/dashboard/public_html/fix-dashboard-pim-final.log"
BACKUP_DIR="/home/dashboard/public_html/backups/dashboard-pim-final-$(date +%Y%m%d_%H%M%S)"

echo "╔══════════════════════════════════════════════════════════════════════════════╗" | tee -a "$LOG_FILE"
echo "║          Final Fix: Dashboard and PIM - Disable Proxy                       ║" | tee -a "$LOG_FILE"
echo "╚══════════════════════════════════════════════════════════════════════════════╝" | tee -a "$LOG_FILE"

mkdir -p "$BACKUP_DIR"

# Disable proxy for dashboard
echo "🔧 Disabling proxy for dashboard..." | tee -a "$LOG_FILE"
proxy_file="/etc/apache2/conf.d/userdata/ssl/2_4/dashboard/dashboard.technostationery.com/proxy.conf"
if [ -f "$proxy_file" ]; then
    cp "$proxy_file" "$BACKUP_DIR/dashboard-proxy.conf.backup"
    cat > "$proxy_file" << 'NOPROXY'
# Proxy disabled - using DocumentRoot only
<IfModule mod_headers.c>
    RequestHeader set X-Forwarded-Proto "https"
    RequestHeader set X-Forwarded-Port "443"
</IfModule>
NOPROXY
    echo "✅ Disabled dashboard proxy" | tee -a "$LOG_FILE"
fi

# Disable proxy for pim
echo "🔧 Disabling proxy for pim..." | tee -a "$LOG_FILE"
proxy_file="/etc/apache2/conf.d/userdata/ssl/2_4/pim/pim.technostationery.com/proxy.conf"
if [ -f "$proxy_file" ]; then
    cp "$proxy_file" "$BACKUP_DIR/pim-proxy.conf.backup"
    cat > "$proxy_file" << 'NOPROXY'
# Proxy disabled - using DocumentRoot only
<IfModule mod_headers.c>
    RequestHeader set X-Forwarded-Proto "https"
    RequestHeader set X-Forwarded-Port "443"
</IfModule>
NOPROXY
    echo "✅ Disabled pim proxy" | tee -a "$LOG_FILE"
fi

# Test and restart
echo "" | tee -a "$LOG_FILE"
echo "🧪 Testing Apache..." | tee -a "$LOG_FILE"
if httpd -t 2>&1 | tee -a "$LOG_FILE"; then
    echo "✅ Apache config valid" | tee -a "$LOG_FILE"
else
    echo "❌ Apache config error!" | tee -a "$LOG_FILE"
    exit 1
fi

echo "" | tee -a "$LOG_FILE"
echo "🔄 Restarting Apache..." | tee -a "$LOG_FILE"
/scripts/rebuildhttpdconf 2>&1 | grep -E "Built|OK" | tee -a "$LOG_FILE"
sleep 2
systemctl restart httpd 2>&1 | tee -a "$LOG_FILE"
sleep 3

echo "" | tee -a "$LOG_FILE"
echo "🧪 Testing..." | tee -a "$LOG_FILE"

# Test dashboard
echo "" | tee -a "$LOG_FILE"
echo "Dashboard:" | tee -a "$LOG_FILE"
curl -k -I -s https://dashboard.technostationery.com/ 2>&1 | head -3 | tee -a "$LOG_FILE"
content=$(curl -k -s https://dashboard.technostationery.com/ 2>&1 | head -30)
if echo "$content" | grep -qi "Index of"; then
    echo "❌ Still directory listing" | tee -a "$LOG_FILE"
elif echo "$content" | grep -qi "<!DOCTYPE html"; then
    echo "✅ Dashboard working!" | tee -a "$LOG_FILE"
fi

# Test PIM
echo "" | tee -a "$LOG_FILE"
echo "PIM:" | tee -a "$LOG_FILE"
curl -k -I -s https://pim.technostationery.com/ 2>&1 | head -3 | tee -a "$LOG_FILE"

echo "" | tee -a "$LOG_FILE"
echo "✅ Complete!" | tee -a "$LOG_FILE"
echo "Log: $LOG_FILE" | tee -a "$LOG_FILE"

