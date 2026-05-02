#!/bin/bash
###############################################################################
# PIM DIAGNOSTIC REPORT
# Run: ./pim_diagnostic.sh
###############################################################################

echo "=========================================="
echo "PIM DIAGNOSTIC REPORT"
echo "Date: $(date)"
echo "=========================================="
echo ""

BASE="https://pim.technostationery.com"
COOKIES=$(mktemp)

echo "=== 1. HOMEPAGE ==="
curl -s -o /dev/null -w "Status: %{http_code}\n" "$BASE/"
echo "Redirects to: $(curl -s -I "$BASE/" | grep -o 'Location: [^ ]*' | head -1)"
echo ""

echo "=== 2. LOGIN PAGE ==="
HTML="$BASE/user/login"
curl -s -c $COOKIES "$HTML" > /tmp/login.html
echo "Status: $(curl -s -o /dev/null -w "%{http_code}" "$HTML")"
echo "Has form: $(grep -c 'login-check' /tmp/login.html || echo 0)"
echo "Has username: $(grep -c '_username' /tmp/login.html || echo 0)"  
echo "Has password: $(grep -c '_password' /tmp/login.html || echo 0)"
echo "Has CSRF: $(grep -c '_csrf_token' /tmp/login.html || echo 0)"
echo "Has submit: $(grep -c 'type=\"submit\"' /tmp/login.html || echo 0)"
echo "Has pim.css: $(grep -c 'pim.css' /tmp/login.html || echo 0)"
echo ""

echo "=== 3. CSS FILE ==="
echo "Status: $(curl -s -o /dev/null -w "%{http_code}" "$BASE/css/pim.css")"
echo "Size: $(curl -s -o /dev/null -w "%{size_download}" "$BASE/css/pim.css") bytes"
echo ""

echo "=== 4. LOGIN FLOW ==="
CSRF=$(grep -oP 'name="_csrf_token"[^>]*value="\K[^"]+' /tmp/login.html | head -1)
echo "CSRF: ${CSRF:0:20}..."

LOGIN_RESP=$(curl -s -b $COOKIES -c $COOKIES -X POST "$BASE/user/login-check" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "_username=bot&_password=@dM1n\$#@2o25B0T&_csrf_token=$CSRF" \
  -L -w "\nStatus: %{http_code}\n" -o /tmp/login_post.html)

echo "Login Response:"
echo "  Has 'dashboard': $(grep -c 'dashboard' /tmp/login_post.html || echo 0)"
echo "  Has 'error': $(grep -c 'error' /tmp/login_post.html || echo 0)"  
echo "  Has 'Invalid': $(grep -c 'Invalid' /tmp/login_post.html || echo 0)"
echo "  Has 'authenticated': $(grep -c 'authenticated' /tmp/login_post.html || echo 0)"
echo "  Has JS (webpack): $(grep -c 'webpack' /tmp/login_post.html || echo 0)"
echo "  Length: $(wc -c < /tmp/login_post.html)"
echo ""

echo "=== 5. DASHBOARD (after login) ==="
DASH_RESP=$(curl -s -b $COOKIES "$BASE/" -w "\nStatus: %{http_code}\n" -o /tmp/dashboard.html)
echo "Dashboard Response:"
echo "  Status: $(echo "$DASH_RESP" | grep Status)"
echo "  Has 'dashboard': $(grep -c 'dashboard' /tmp/dashboard.html || echo 0)"
echo "  Has 'enrich': $(grep -c 'enrich' /tmp/dashboard.html || echo 0)"
echo "  Has 'Akeneo': $(grep -c 'Akeneo' /tmp/dashboard.html || echo 0)"
echo "  Has 'products': $(grep -c 'products' /tmp/dashboard.html || echo 0)"
echo "  Length: $(wc -c < /tmp/dashboard.html)"
echo ""

echo "=== 6. COOKIES AFTER LOGIN ==="
cat $COOKIES
echo ""

echo "=== 7. DATABASE ==="
/opt/mariadb10.6/mariadb/bin/mysql -u akeneo_pim -pakeneo_pim -h 127.0.0.1 -P 3307 akeneo_pim -e "SELECT COUNT(*) as products FROM akeneo_catalog_product;" 2>/dev/null || echo "Connection test: Need to check"
echo ""

echo "=== 8. ELASTICSEARCH ==="
curl -s localhost:9200/akeneo_pim_product_and_product_model_01092bbe*/_count 2>/dev/null | grep -o '"count":[0-9]*' || echo "Check ES manually"
echo ""

echo "=== 9. API TEST (needs OAuth) ==="
echo "Without auth: $(curl -s -o /dev/null -w '%{http_code}' "$BASE/api/rest/v1/products?limit=1")"
echo ""

echo "=========================================="
echo "DIAGNOSTIC COMPLETE"
echo "=========================================="
echo ""
echo "Files saved:"
echo "  /tmp/login.html"
echo "  /tmp/login_post.html" 
echo "  /tmp/dashboard.html"
echo "  $COOKIES (cookies)"
echo ""
echo "Next steps:"
echo "1. Check login_post.html for actual response"
echo "2. Verify credentials are correct"
echo "3. Check if JS bundles are loading in dashboard"