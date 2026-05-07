#!/bin/bash
echo "=== Deep Analysis of PIM Redirect Issue ==="
echo ""

# Check the actual SSL vhost file (not just the include)
echo "1. Main SSL VHost File:"
grep -A 30 "pim.technostationery.com:443" /etc/apache2/conf/httpd.conf 2>/dev/null | head -40 || echo "Not found in main config"

echo ""
echo "2. Check all include files for pim:"
find /etc/apache2/conf.d/ -name "*.conf" -exec grep -l "pim.technostationery.com" {} \; 2>/dev/null

echo ""
echo "3. Direct backend test (no Cloudflare):"
echo "Testing https://209.126.117.105/ with Host header..."
curl -sI -k https://209.126.117.105/ -H "Host: pim.technostationery.com" 2>&1 | head -15

echo ""
echo "4. Test with different paths:"
curl -sI https://pim.technostationery.com/ --max-redirs 0 2>&1 | grep -E "(HTTP|location:)" || echo "No redirect"
curl -sI https://pim.technostationery.com/index.php --max-redirs 0 2>&1 | grep -E "(HTTP|location:)" || echo "No redirect"

echo ""
echo "5. Check if Cloudflare is adding redirects:"
echo "Cloudflare headers:"
curl -sI https://pim.technostationery.com/ 2>&1 | grep -i "cf-"

