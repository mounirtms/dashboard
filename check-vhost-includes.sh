#!/bin/bash
echo "=== Checking Apache VHost Configuration ==="
echo ""

echo "1. Find PIM SSL vhost line numbers:"
grep -n "ServerName pim.technostationery.com" /etc/apache2/conf/httpd.conf | head -5

echo ""
echo "2. Extract SSL vhost block (line ~1727):"
sed -n '1720,1800p' /etc/apache2/conf/httpd.conf | grep -A 20 "ServerName pim.technostationery.com" | head -30

echo ""
echo "3. Check for Include directives in SSL vhost:"
sed -n '1720,1800p' /etc/apache2/conf/httpd.conf | grep -E "(Include|DocumentRoot|Directory)" | head -10

echo ""
echo "4. List all userdata includes for pim:"
ls -la /etc/apache2/conf.d/userdata/ssl/2_4/pim/pim.technostationery.com/

echo ""
echo "5. Check what's in each include file:"
for file in /etc/apache2/conf.d/userdata/ssl/2_4/pim/pim.technostationery.com/*.conf; do
    if [ -f "$file" ]; then
        echo "File: $file"
        cat "$file"
        echo "---"
    fi
done

