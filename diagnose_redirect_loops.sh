#!/bin/bash
##########################################################
# Diagnose and Fix ERR_TOO_MANY_REDIRECTS Issue
##########################################################

echo "=== REDIRECT LOOP DIAGNOSIS ==="
echo ""

echo "1. Checking SSL certificates for subdomains:"
echo "-------------------------------------------"
for domain in beta.technostationery.com dev.technostationery.com dashboard.technostationery.com lms.technostationery.com pim.technostationery.com; do
    echo "Testing: $domain"
    if openssl s_client -connect $domain:443 -servername $domain </dev/null 2>/dev/null | grep -q "Verify return code: 0"; then
        echo "  ✓ SSL certificate valid"
    else
        echo "  ✗ SSL certificate issue"
    fi
done

echo ""
echo "2. Checking Apache vhost SSL configuration:"
echo "-------------------------------------------"
grep -r "beta.technostationery.com" /etc/apache2/conf.d/vhosts/ 2>/dev/null | grep -i "ssl\|443" | head -5

echo ""
echo "3. Checking for redirect loops in Apache config:"
echo "------------------------------------------------"
grep -r "Redirect" /etc/apache2/conf.d/includes/port80-redirects.conf 2>/dev/null | head -10

echo ""
echo "4. Testing Cloudflare SSL Mode (via API):"
echo "------------------------------------------"
curl -s -X GET "https://api.cloudflare.com/client/v4/zones/4919ad3406fcabba381edbd543814a68/settings/ssl" \
  -H "X-Auth-Email: webmaster@techno-dz.com" \
  -H "X-Auth-Key: 35d8fd4b1a5d27eabbce73c6753978fc350bc" \
  -H "Content-Type: application/json" | python3 -m json.tool 2>/dev/null || echo "API call failed"

echo ""
echo "5. Checking for X-Forwarded-Proto handling:"
echo "-------------------------------------------"
grep -r "X-Forwarded-Proto\|X-Forwarded-SSL" /etc/apache2/conf.d/ 2>/dev/null | head -10

echo ""
echo "6. Testing actual redirect behavior:"
echo "------------------------------------"
for domain in beta.technostationery.com dashboard.technostationery.com; do
    echo "Testing: $domain"
    curl -sI -L --max-redirs 5 https://$domain/ 2>&1 | grep -E "HTTP|location:" | head -10
    echo ""
done
