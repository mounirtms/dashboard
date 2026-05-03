#!/bin/bash
# Comprehensive Varnish Cache Warmup Script

BASE_URL="http://localhost"
LOG_FILE="/home/dashboard/public_html/logs/varnish_warmup.log"

echo "=== Varnish Cache Warmup Started: $(date) ===" | tee -a "$LOG_FILE"

# Main pages
PAGES=(
    "/"
    "/index.php"
    "/customer/account/login"
    "/customer/account/create"
    "/checkout/cart"
)

# Warm up main pages
echo "Warming up main pages..." | tee -a "$LOG_FILE"
for page in "${PAGES[@]}"; do
    response=$(curl -s -o /dev/null -w "%{http_code}" -H "Host: technostationery.com" "$BASE_URL$page" 2>&1)
    echo "  $page - HTTP $response" | tee -a "$LOG_FILE"
    sleep 0.1
done

# Warm up static assets
echo "Warming up static assets..." | tee -a "$LOG_FILE"
for i in {1..20}; do
    curl -s -o /dev/null -H "Host: technostationery.com" "$BASE_URL/static/version123456789/frontend/theme/file_$i.css" 2>&1
    curl -s -o /dev/null -H "Host: technostationery.com" "$BASE_URL/static/version123456789/frontend/theme/file_$i.js" 2>&1
done

# Warm up product pages (simulate)
echo "Warming up product pages..." | tee -a "$LOG_FILE"
for i in {1..10}; do
    curl -s -o /dev/null -H "Host: technostationery.com" "$BASE_URL/product-$i.html" 2>&1
    sleep 0.1
done

# Warm up category pages
echo "Warming up category pages..." | tee -a "$LOG_FILE"
for i in {1..5}; do
    curl -s -o /dev/null -H "Host: technostationery.com" "$BASE_URL/category-$i.html" 2>&1
    sleep 0.1
done

echo "=== Warmup Completed: $(date) ===" | tee -a "$LOG_FILE"

# Show stats
echo "" | tee -a "$LOG_FILE"
echo "Current Varnish Stats:" | tee -a "$LOG_FILE"
varnishstat -1 | grep -E "cache_hit|cache_miss|client_req" | grep -v "cache_hit_grace" | tee -a "$LOG_FILE"
