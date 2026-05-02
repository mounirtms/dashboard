#!/bin/bash
# Varnish Cache Warmup - Beta Magento Site
# Lighter warmup for testing environment - Targets Varnish port 6081

set -e

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║     VARNISH WARMUP - BETA MAGENTO                              ║"
echo "╚════════════════════════════════════════════════════════════════╝"

START_TIME=$(date +%s)
LOG_FILE="/home/dashboard/logs/varnish_warmup_beta_$(date +%Y%m%d_%H%M%S).log"

exec > >(tee -a "$LOG_FILE")
exec 2>&1

echo "Started: $(date)"
echo ""

# Configuration - Target Varnish directly on port 6081
VARNISH_PORT=6081
SITE_HOST="beta.technostationery.com"
DB_HOST="localhost"
DB_USER="beta_ntdbusr24"
DB_PASS="BetaTechno2024!"
DB_NAME="beta_dBT8x12y22"
REQUEST_DELAY=0.05

# Counters
WARMED=0
FAILED=0
TOTAL=0

# ============================================================================
# PHASE 1: CRITICAL BETA PAGES
# ============================================================================
echo "=== PHASE 1: BETA CRITICAL PAGES ==="
echo "Targeting: http://localhost:${VARNISH_PORT} (Varnish)"
echo ""

URLS=(
    "/"
    "/customer/account/login"
    "/customer/account/create"
    "/checkout/cart"
    "/catalogsearch/result"
    "/catalogsearch/advanced"
)

for URL in "${URLS[@]}"; do
    TOTAL=$((TOTAL + 1))
    
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
        -H "Host: $SITE_HOST" \
        -H "User-Agent: VarnishWarmup/1.0" \
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
done

echo "Phase 1: ${WARMED}/${TOTAL} pages warmed"
echo ""

# ============================================================================
# PHASE 2: TOP CATEGORIES (Limited for Beta)
# ============================================================================
echo "=== PHASE 2: BETA CATEGORIES ==="

if command -v mysql &> /dev/null; then
    CATEGORIES=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -N -e "
        SELECT DISTINCT CONCAT('/', cev.value) as url
        FROM catalog_category_entity_varchar cev
        INNER JOIN eav_attribute ea ON cev.attribute_id = ea.attribute_id
        WHERE ea.attribute_code = 'url_path'
        AND ea.entity_type_id = 3
        AND cev.value IS NOT NULL
        LIMIT 10;
    " 2>/dev/null || echo "")
    
    if [ -n "$CATEGORIES" ]; then
        while IFS= read -r URL; do
            [ -z "$URL" ] && continue
            TOTAL=$((TOTAL + 1))
            
            HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
                -H "Host: $SITE_HOST" \
                -H "X-Forwarded-For: 127.0.0.1" \
                "http://localhost:${VARNISH_PORT}${URL}" \
                --max-time 15)
            
            if [ "$HTTP_CODE" = "200" ]; then
                WARMED=$((WARMED + 1))
                echo "  ✓ ${URL}"
            else
                FAILED=$((FAILED + 1))
            fi
            
            sleep $REQUEST_DELAY
        done <<< "$CATEGORIES"
    else
        echo "No categories found"
    fi
else
    echo "MySQL not available"
fi

echo ""

# ============================================================================
# FINAL STATISTICS
# ============================================================================
END_TIME=$(date +%s)
DURATION=$((END_TIME - START_TIME))
SUCCESS_RATE=$(awk "BEGIN {printf \"%.1f\", ($WARMED / $TOTAL) * 100}")

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║              BETA WARMUP COMPLETE                              ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""
echo "Summary:"
echo "  Total URLs:       $TOTAL"
echo "  Successfully warmed: $WARMED"
echo "  Failed:           $FAILED"
echo "  Success rate:     ${SUCCESS_RATE}%"
echo "  Duration:         ${DURATION}s"
echo ""

# Log summary
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Beta warmup: ${WARMED}/${TOTAL} (${SUCCESS_RATE}%), ${DURATION}s" >> /home/dashboard/logs/varnish_warmup.log

echo "✓ Beta warmup completed"
echo "Log: $LOG_FILE"
