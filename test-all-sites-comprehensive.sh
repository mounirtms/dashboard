#!/bin/bash
# Comprehensive Site Testing Script
# Tests all sites, Varnish, and infrastructure

echo "=== Comprehensive Infrastructure Test ==="
echo "Date: $(date)"
echo ""

# Test all sites
echo "=== Testing All Sites ==="
SITES=(
  "https://technostationery.com/"
  "https://beta.technostationery.com/"
  "https://dev.technostationery.com/"
  "https://lms.technostationery.com/"
  "https://dashboard.technostationery.com/"
  "https://pim.technostationery.com/"
)

for site in "${SITES[@]}"; do
  echo -n "Testing $site ... "
  STATUS=$(curl -sI "$site" | grep -E "^HTTP" | awk '{print $2}')
  TITLE=$(curl -s "$site" | grep -oP '<title>\K[^<]+' | head -1)
  
  if [[ "$STATUS" == "200" ]]; then
    echo "✅ HTTP $STATUS - $TITLE"
  elif [[ "$STATUS" == "301" ]] || [[ "$STATUS" == "302" ]]; then
    echo "⚠️  HTTP $STATUS (Redirect)"
  else
    echo "❌ HTTP $STATUS"
  fi
done

echo ""
echo "=== Testing Varnish API ==="
echo -n "Varnish Overview API ... "
VARNISH_RESP=$(curl -s "https://dashboard.technostationery.com/api/varnish.php?action=overview")
if echo "$VARNISH_RESP" | grep -q '"success":true'; then
  HIT_RATE=$(echo "$VARNISH_RESP" | grep -oP '"hit_rate":"[0-9.]+' | grep -oP '[0-9.]+' || echo "0")
  echo "✅ Working - Hit Rate: ${HIT_RATE}%"
else
  echo "❌ Failed"
fi

echo ""
echo "=== Varnish Statistics ==="
varnishstat -1 | grep -E "cache_(hit|miss)|client_req|backend" | head -10

echo ""
echo "=== Port Status ==="
netstat -tlnp | grep -E ":(80|81|443|8888|6082)" | awk '{print $4, $7}' | sort -u

echo ""
echo "=== Apache Status ==="
systemctl is-active httpd && echo "✅ Apache Running" || echo "❌ Apache Stopped"

echo ""
echo "=== Varnish Status ==="
systemctl is-active varnish && echo "✅ Varnish Running" || echo "❌ Varnish Stopped"

echo ""
echo "=== System Resources ==="
echo "CPU Load: $(uptime | awk -F'load average:' '{print $2}')"
echo "Memory: $(free -h | awk '/^Mem:/ {print $3 " / " $2}')"
echo "Disk: $(df -h /home | awk 'NR==2 {print $3 " / " $2 " (" $5 " used)"}')"

echo ""
echo "=== Test Complete ==="
