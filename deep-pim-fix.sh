#!/bin/bash
echo "=== Deep PIM Redirect Fix ==="
echo "Date: $(date)"
echo ""

# Step 1: Check if there's a Page Rule issue
echo "1. Testing with cache bypass headers:"
curl -sI https://pim.technostationery.com/ \
  -H "Cache-Control: no-cache" \
  -H "Pragma: no-cache" \
  --max-redirs 0 2>&1 | grep -E "(HTTP|location:)" | head -3

echo ""
echo "2. Check if DocumentRoot is actually being used:"
grep -A 5 "ServerName pim.technostationery.com" /etc/apache2/conf/httpd.conf | grep DocumentRoot | head -3

echo ""
echo "3. Check main SSL vhost for pim:"
awk '/ServerName pim\.technostationery\.com/,/<\/VirtualHost>/' /etc/apache2/conf/httpd.conf 2>/dev/null | grep -E "(DocumentRoot|Redirect|RewriteRule.*301|ServerName)" | head -10

echo ""
echo "4. Test if index.php is accessible directly:"
curl -sI http://127.0.0.1:81/index.php -H "Host: pim.technostationery.com" 2>&1 | head -5

echo ""
echo "5. Check what files exist in public directory:"
ls -la /home/pim/public_html/public/ | grep -E "(index|php)" | head -10

echo ""
echo "6. Test direct file access:"
if [ -f /home/pim/public_html/public/index.php ]; then
    echo "index.php exists: YES"
    head -5 /home/pim/public_html/public/index.php
else
    echo "index.php exists: NO"
fi

echo ""
echo "7. Check Apache error logs for pim:"
tail -20 /var/log/apache2/error_log | grep -i pim || echo "No recent PIM errors"

echo ""
echo "8. Test with different URL paths:"
echo "Root:"
curl -sI http://127.0.0.1:81/ -H "Host: pim.technostationery.com" 2>&1 | head -1
echo "Index.php:"
curl -sI http://127.0.0.1:81/index.php -H "Host: pim.technostationery.com" 2>&1 | head -1
echo "Admin:"
curl -sI http://127.0.0.1:81/admin -H "Host: pim.technostationery.com" 2>&1 | head -1

