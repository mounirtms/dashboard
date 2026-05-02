#!/bin/bash
# Varnish Cache Warmup - Production Magento Site
# Optimized for maximum hit rate - Targets Varnish port 6081

set -e

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║     VARNISH WARMUP - PRODUCTION MAGENTO                        ║"
echo "╚════════════════════════════════════════════════════════════════╝"

START_TIME=$(date +%s)
LOG_FILE="/home/dashboard/logs/varnish_warmup_prod_$(date +%Y%m%d_%H%M%S).log"

exec > >(tee -a "$LOG_FILE")
exec 2>&1

echo "Started: $(date)"
echo ""

# Configuration - Target Varnish directly on port 6081
VARNISH_PORT=6081
SITE_HOST="technostationery.com"
MAGENTO_ROOT="/home/technadminy7/public_html"
DB_HOST="localhost"
DB_USER="technadminy7_ntdbusr24"
DB_PASS="Techno2024!"
DB_NAME="technadminy7_dBT8x12y22"
CONCURRENT_REQUESTS=5
REQUEST_DELAY=0.05

# Counters
WARMED=0
FAILED=0
TOTAL=0

# Temp files
URLS_FILE="/tmp/varnish_warmup_urls_$$$.txt"
CATEGORIES_FILE="/tmp/varnish_categories_$$$.txt"
PRODUCTS_FILE="/tmp/varnish_products_$$$.txt"

# Cleanup on exit
trap "rm -f $URLS_FILE $CATEGORIES_FILE $PRODUCTS_FILE" EXIT

# ============================================================================
# PHASE 1: CRITICAL PAGES
# ============================================================================
echo "=== PHASE 1: CRITICAL PAGES ==="
echo "Targeting: http://localhost:${VARNISH_PORT} (Varnish)"
echo ""

cat > "$URLS_FILE" << 'CRITICALURLS'
/
/customer/account/login
/customer/account/create
/customer/account/forgotpassword
/checkout/cart
/checkout
/catalogsearch/advanced
/about-us
/contact-us
/privacy-policy
/terms-conditions
/faq
/sitemap
CRITICALURLS

while IFS= read -r URL; do
    [ -z "$URL" ] && continue
    TOTAL=$((TOTAL + 1))
    
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
        -H "Host: $SITE_HOST" \
        -H "User-Agent: VarnishWarmup/1.0" \
        -H "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8" \
        -H "X-Forwarded-For: 127.0.0.1" \
        -H "X-Forwarded-Proto: https" \
        "http://localhost:${VARNISH_PORT}${URL}" \
        --max-time 15 \
        --connect-timeout 5)
    
    if [ "$HTTP_CODE" = "200" ]; then
        WARMED=$((WARMED + 1))
        echo "  ✓ ${URL} (${HTTP_CODE})"
    else
        FAILED=$((FAILED + 1))
        echo "  ✗ ${URL} (${HTTP_CODE})"
    fi
    
    sleep $REQUEST_DELAY
done < "$URLS_FILE"

echo "Phase 1: ${WARMED}/${TOTAL} pages warmed"
echo ""

# ============================================================================
# PHASE 2: TOP CATEGORIES (From Database)
# ============================================================================
echo "=== PHASE 2: TOP CATEGORIES ==="

# Extract category URLs from Magento database
if command -v mysql &> /dev/null; then
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -N -e "
        SELECT DISTINCT CONCAT('/', cev.value) as url
        FROM catalog_category_entity_varchar cev
        INNER JOIN eav_attribute ea ON cev.attribute_id = ea.attribute_id
        WHERE ea.attribute_code = 'url_path'
        AND ea.entity_type_id = 3
        AND cev.value IS NOT NULL
        AND cev.value != ''
        ORDER BY cev.entity_id DESC
        LIMIT 30;
    " > "$CATEGORIES_FILE" 2>/dev/null || echo "Database query failed"
    
    if [ -s "$CATEGORIES_FILE" ]; then
        CAT_COUNT=0
        while IFS= read -r URL; do
            [ -z "$URL" ] && continue
            TOTAL=$((TOTAL + 1))
            CAT_COUNT=$((CAT_COUNT + 1))
            
            HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
                -H "Host: $SITE_HOST" \
                -H "User-Agent: VarnishWarmup/1.0" \
                -H "X-Forwarded-For: 127.0.0.1" \
                "http://localhost:${VARNISH_PORT}${URL}" \
                --max-time 15 \
                --connect-timeout 5)
            
            if [ "$HTTP_CODE" = "200" ]; then
                WARMED=$((WARMED + 1))
                echo "  ✓ Category: ${URL} (${HTTP_CODE})"
            else
                FAILED=$((FAILED + 1))
                echo "  ✗ Category: ${URL} (${HTTP_CODE})"
            fi
            
            sleep $REQUEST_DELAY
        done < "$CATEGORIES_FILE"
        
        echo "Phase 2: ${CAT_COUNT} categories processed"
    else
        echo "No categories found in database"
    fi
else
    echo "MySQL not available, skipping category warmup"
fi

echo ""

# ============================================================================
# PHASE 3: TOP PRODUCTS (Best Sellers)
# ============================================================================
echo "=== PHASE 3: TOP PRODUCTS ==="

if command -v mysql &> /dev/null; then
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -N -e "
        SELECT DISTINCT CONCAT('/', cev.value, '.html') as url
        FROM catalog_product_entity_varchar cev
        INNER JOIN eav_attribute ea ON cev.attribute_id = ea.attribute_id
        WHERE ea.attribute_code = 'url_key'
        AND ea.entity_type_id = 4
        AND cev.value IS NOT NULL
        AND cev.value != ''
        ORDER BY cev.entity_id DESC
        LIMIT 50;
    " > "$PRODUCTS_FILE" 2>/dev/null || echo "Product query failed"
    
    if [ -s "$PRODUCTS_FILE" ]; then
        PROD_COUNT=0
        while IFS= read -r URL; do
            [ -z "$URL" ] && continue
            TOTAL=$((TOTAL + 1))
            PROD_COUNT=$((PROD_COUNT + 1))
            
            HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
                -H "Host: $SITE_HOST" \
                -H "User-Agent: VarnishWarmup/1.0" \
                -H "X-Forwarded-For: 127.0.0.1" \
                "http://localhost:${VARNISH_PORT}${URL}" \
                --max-time 15 \
                --connect-timeout 5)
            
            if [ "$HTTP_CODE" = "200" ]; then
                WARMED=$((WARMED + 1))
                echo "  ✓ Product: ${URL} (${HTTP_CODE})"
            else
                FAILED=$((FAILED + 1))
                # Don't log every failed product to avoid spam
            fi
            
            sleep $REQUEST_DELAY
        done < "$PRODUCTS_FILE"
        
        echo "Phase 3: ${PROD_COUNT} products processed"
    else
        echo "No products found in database"
    fi
else
    echo "MySQL not available, skipping product warmup"
fi

echo ""

# ============================================================================
# PHASE 4: STATIC ASSETS (CSS, JS, Images)
# ============================================================================
echo "=== PHASE 4: STATIC ASSETS ==="

# Common static assets
STATIC_ASSETS=(
    "/static/frontend/Smartwave/porto/en_US/css/styles-m.css"
    "/static/frontend/Smartwave/porto/en_US/css/styles-l.css"
    "/static/frontend/Smartwave/porto/en_US/js/theme.js"
    "/static/version1234/js/require.js"
    "/media/logo/default/logo.png"
    "/skin/frontend/default/default/css/styles.css"
)

STATIC_COUNT=0
for ASSET in "${STATIC_ASSETS[@]}"; do
    TOTAL=$((TOTAL + 1))
    STATIC_COUNT=$((STATIC_COUNT + 1))
    
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
        -H "Host: $SITE_HOST" \
        -H "X-Forwarded-For: 127.0.0.1" \
        "http://localhost:${VARNISH_PORT}${ASSET}" \
        --max-time 10 \
        --connect-timeout 5)
    
    if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "304" ]; then
        WARMED=$((WARMED + 1))
        echo "  ✓ Asset: ${ASSET} (${HTTP_CODE})"
    fi
    
    sleep $REQUEST_DELAY
done

echo "Phase 4: ${STATIC_COUNT} static assets processed"
echo ""

# ============================================================================
# FINAL STATISTICS
# ============================================================================
END_TIME=$(date +%s)
DURATION=$((END_TIME - START_TIME))
SUCCESS_RATE=$(awk "BEGIN {printf \"%.1f\", ($WARMED / $TOTAL) * 100}")

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║                    WARMUP COMPLETE                             ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""
echo "Summary:"
echo "  Total URLs:       $TOTAL"
echo "  Successfully warmed: $WARMED"
echo "  Failed:           $FAILED"
echo "  Success rate:     ${SUCCESS_RATE}%"
echo "  Duration:         ${DURATION}s"
echo "  Completed:        $(date)"
echo ""

# Check Varnish hit rate after warmup
if command -v varnishstat &> /dev/null; then
    echo "Current Varnish Stats:"
    varnishstat -1 | grep -E "cache_hit|cache_miss" | head -5
    echo ""
fi

# Log summary to main log
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Production warmup: ${WARMED}/${TOTAL} (${SUCCESS_RATE}%), ${DURATION}s" >> /home/dashboard/logs/varnish_warmup.log

echo "✓ Production warmup completed"
echo "Log: $LOG_FILE"
