#!/bin/bash
echo "=== PIM Redirect Diagnosis ==="
echo "Date: $(date)"
echo ""

# Test 1: Direct origin server (bypass Cloudflare)
echo "1. Testing origin server directly (port 443):"
timeout 5 curl -sIk https://209.126.117.105/ -H "Host: pim.technostationery.com" 2>&1 | grep -E "(HTTP|location:)" | head -5

echo ""
echo "2. Testing backend port 81:"
curl -sI http://127.0.0.1:81/ -H "Host: pim.technostationery.com" 2>&1 | grep -E "(HTTP|location:)" | head -5

echo ""
echo "3. Check Apache vhost for redirects:"
grep -r "Redirect\|RewriteRule.*301\|RewriteRule.*https" /etc/apache2/conf.d/userdata/ssl/2_4/pim/ 2>/dev/null || echo "No redirects in SSL userdata"

echo ""
echo "4. Check main vhost config:"
grep -A 20 "pim.technostationery.com:443" /etc/apache2/conf/httpd.conf 2>/dev/null | grep -E "Redirect|RewriteRule|ServerName|DocumentRoot" | head -10

echo ""
echo "5. Check for index.php redirect:"
curl -sI http://127.0.0.1:81/index.php -H "Host: pim.technostationery.com" 2>&1 | grep -E "(HTTP|location:)" | head -3

echo ""
echo "6. List all Apache configs for pim:"
find /etc/apache2/conf.d/ -type f -name "*.conf" -exec grep -l "pim.technostationery.com" {} \; 2>/dev/null

