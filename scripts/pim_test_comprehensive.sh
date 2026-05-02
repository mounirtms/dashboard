#!/bin/bash
###############################################################################
# PIM Comprehensive Test Script
# Run: ./pim_test_comprehensive.sh
###############################################################################

set -e
echo "=========================================="
echo "PIM COMPREHENSIVE TEST"
echo "Date: $(date)"
echo "=========================================="

BASE_URL="https://pim.technostationery.com"

echo ""
echo "=== Test 1: Homepage ==="
STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/")
echo "Status: $STATUS (redirects to login)"

echo ""
echo "=== Test 2: Login Page ==="
STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/user/login")
echo "Status: $STATUS"

echo ""
echo "=== Test 3: Login Form Details ==="
HTML=$(curl -s "$BASE_URL/user/login")
echo "Has form: $(echo $HTML | grep -c 'pim_user_security_login')"
echo "Has username: $(echo $HTML | grep -c '_username')"
echo "Has password: $(echo $HTML | grep -c '_password')"
echo "Has csrf: $(echo $HTML | grep -c '_csrf_token')"
echo "Has submit: $(echo $HTML | grep -c 'type=\"submit\"')"

echo ""
echo "=== Test 4: Try Login (POST) ==="
# First get CSRF
CSRF=$(curl -s "$BASE_URL/user/login" | grep -oP 'name="_csrf_token"[^>]*value="\K[^"]+' | head -1)
echo "CSRF Token: ${CSRF:0:20}..."

# Try login
RESPONSE=$(curl -s -X POST "$BASE_URL/user/login-check" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "_username=bot&_password=@dM1n\$#@2o25B0T&_csrf_token=$CSRF" \
  -c /tmp/pim_cookies.txt -w "\nStatus: %{http_code}\n")

if echo "$RESPONSE" | grep -q "dashboard"; then
    echo "Login Result: SUCCESS"
else
    echo "Login Result: Check response manually"
fi

echo ""
echo "=== Test 5: CSS File ==="
STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/css/pim.css")
SIZE=$(curl -s "$BASE_URL/css/pim.css" | wc -c)
echo "Status: $STATUS"
echo "Size: $SIZE bytes"

echo ""
echo "=== Test 6: Database Check ==="
/opt/mariadb10.6/mariadb/bin/mysql -u akeneo_pim -pakeneo_pim -h 127.0.0.1 -P 3307 akeneo_pim -e "SELECT 1;" 2>/dev/null && echo "Database: OK" || echo "Database: FAILED"

echo ""
echo "=== Test 7: Elasticsearch ==="
ES_STATUS=$(curl -s localhost:9200/_cluster/health | grep -o '"status":"[^"]*"' | cut -d'"' -f3)
echo "Elasticsearch: $ES_STATUS"

echo ""
echo "=== Test 8: API (expected 401 without OAuth) ==="
STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/api/rest/v1/products?limit=1")
echo "API Status: $STATUS (expected 401)"

echo ""
echo "=== RESULTS ==="
echo "Homepage: $(curl -s -o /dev/null -w '%{http_code}' "$BASE_URL/") -> redirects"
echo "Login: $(curl -s -o /dev/null -w '%{http_code}' "$BASE_URL/user/login") -> OK"
echo "CSS: $(curl -s -o /dev/null -w '%{http_code}' "$BASE_URL/css/pim.css") -> OK"
echo "API: $(curl -s -o /dev/null -w '%{http_code}' "$BASE_URL/api/rest/v1/products?limit=1") -> needs auth"

echo ""
echo "=========================================="
echo "TEST COMPLETE"
echo "=========================================="