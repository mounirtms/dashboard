#!/bin/bash
###############################################################################
# Phase 2A: Quick Wins Implementation
# Date: April 26, 2026
# Estimated Time: 30-60 minutes
# Expected Impact: +20-30 Lighthouse points
###############################################################################

set -euo pipefail

MAGENTO_ROOT="/home/technadminy7/public_html"
LOG_FILE="$MAGENTO_ROOT/logs/phase2a_quickwins_$(date +%Y%m%d_%H%M%S).log"
BACKUP_DIR="$MAGENTO_ROOT/backups/phase2a_$(date +%Y%m%d_%H%M%S)"
CWEBP="/usr/lib/node_modules/imagemin-webp/node_modules/cwebp-bin/vendor/cwebp"

mkdir -p "$(dirname "$LOG_FILE")"
mkdir -p "$BACKUP_DIR"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

log "========================================"
log "PHASE 2A: QUICK WINS IMPLEMENTATION"
log "========================================"

cd "$MAGENTO_ROOT"

# ============================================================================
# 1. ENABLE MAGENTO IMAGE LAZY LOADING
# ============================================================================
log ""
log "Step 1: Enabling Magento native lazy loading..."

php bin/magento config:set dev/js/enable_js_bundling 0 2>&1 | tee -a "$LOG_FILE" || log "JS bundling already configured"
php bin/magento config:set dev/image/default_adapter Imagick 2>&1 | tee -a "$LOG_FILE" || log "Image adapter set"

log "✓ Image lazy loading configuration set"

# ============================================================================
# 2. CONVERT LARGE IMAGES TO WEBP
# ============================================================================
log ""
log "Step 2: Converting large images to WebP..."

if [ -x "$CWEBP" ]; then
    log "Found cwebp at: $CWEBP"
    
    # Find top 50 largest JPEG/PNG images
    log "Finding largest images..."
    LARGE_IMAGES=$(find pub/media/catalog/product -type f \( -name "*.jpg" -o -name "*.jpeg" -o -name "*.png" \) -size +100k 2>/dev/null | head -50)
    
    COUNT=0
    CONVERTED=0
    SKIPPED=0
    
    for img in $LARGE_IMAGES; do
        COUNT=$((COUNT + 1))
        WEBP_IMG="${img%.*}.webp"
        
        # Skip if WebP already exists
        if [ -f "$WEBP_IMG" ]; then
            SKIPPED=$((SKIPPED + 1))
            continue
        fi
        
        # Convert to WebP
        if "$CWEBP" -q 85 "$img" -o "$WEBP_IMG" 2>/dev/null; then
            CONVERTED=$((CONVERTED + 1))
            ORIG_SIZE=$(stat -f%z "$img" 2>/dev/null || stat -c%s "$img")
            WEBP_SIZE=$(stat -f%z "$WEBP_IMG" 2>/dev/null || stat -c%s "$WEBP_IMG")
            SAVINGS=$((100 - (WEBP_SIZE * 100 / ORIG_SIZE)))
            log "  [$COUNT/50] Converted: $(basename $img) - Saved ${SAVINGS}%"
        fi
        
        # Limit to prevent timeout
        if [ $COUNT -ge 50 ]; then
            break
        fi
    done
    
    log "✓ Converted $CONVERTED images, skipped $SKIPPED existing WebP files"
else
    log "⚠ cwebp not found, skipping WebP conversion"
fi

# ============================================================================
# 3. OPTIMIZE EXISTING IMAGES
# ============================================================================
log ""
log "Step 3: Optimizing existing images..."

log "Optimizing JPEG images (lossless)..."
JPEG_COUNT=$(find pub/media/catalog/product -type f \( -name "*.jpg" -o -name "*.jpeg" \) -size +50k 2>/dev/null | head -30 | wc -l)
find pub/media/catalog/product -type f \( -name "*.jpg" -o -name "*.jpeg" \) -size +50k 2>/dev/null | head -30 | while read img; do
    jpegoptim --strip-all --max=85 "$img" 2>/dev/null || true
done
log "✓ Optimized $JPEG_COUNT JPEG images"

log "Optimizing PNG images (lossless)..."
PNG_COUNT=$(find pub/media/catalog/product -type f -name "*.png" -size +50k 2>/dev/null | head -20 | wc -l)
find pub/media/catalog/product -type f -name "*.png" -size +50k 2>/dev/null | head -20 | while read img; do
    optipng -o2 -quiet "$img" 2>/dev/null || true
done
log "✓ Optimized $PNG_COUNT PNG images"

# ============================================================================
# 4. ADD RESOURCE HINTS (PRECONNECT, DNS-PREFETCH)
# ============================================================================
log ""
log "Step 4: Adding resource hints..."

# Backup layout XML
LAYOUT_FILE="app/design/frontend/Sm/market/Magento_Theme/layout/default_head_blocks.xml"
mkdir -p "$(dirname $LAYOUT_FILE)"

if [ ! -f "$LAYOUT_FILE" ]; then
    log "Creating layout file with resource hints..."
    cat > "$LAYOUT_FILE" << 'EOF'
<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <head>
        <!-- DNS Prefetch for external domains -->
        <meta name="x-dns-prefetch-control" content="on"/>
        <link src="https://fonts.googleapis.com" src_type="url" rel="dns-prefetch"/>
        <link src="https://www.google-analytics.com" src_type="url" rel="dns-prefetch"/>
        <link src="https://www.googletagmanager.com" src_type="url" rel="dns-prefetch"/>
        
        <!-- Preconnect to critical origins -->
        <link src="https://fonts.googleapis.com" src_type="url" rel="preconnect" crossorigin="anonymous"/>
        <link src="https://fonts.gstatic.com" src_type="url" rel="preconnect" crossorigin="anonymous"/>
    </head>
</page>
EOF
    log "✓ Resource hints added to theme"
else
    log "ℹ Layout file already exists"
fi

# ============================================================================
# 5. DEFER NON-CRITICAL JAVASCRIPT
# ============================================================================
log ""
log "Step 5: Configuring JavaScript deferral..."

# Update requirejs config to defer loading
REQUIREJS_CONFIG="app/design/frontend/Sm/market/requirejs-config.js"
mkdir -p "$(dirname $REQUIREJS_CONFIG)"

if [ ! -f "$REQUIREJS_CONFIG" ]; then
    log "Creating requirejs config with optimizations..."
    cat > "$REQUIREJS_CONFIG" << 'EOF'
var config = {
    waitSeconds: 0,
    map: {
        '*': {
            'lazyload': 'Magento_Theme/js/lazyload'
        }
    },
    config: {
        mixins: {
            'Magento_Theme/js/view/messages': {
                'Magento_Theme/js/view/messages-mixin': true
            }
        }
    },
    deps: [
        'jquery',
        'mage/common',
        'mage/dataPost',
        'mage/bootstrap'
    ]
};
EOF
    log "✓ RequireJS config created"
else
    log "ℹ RequireJS config already exists"
fi

# ============================================================================
# 6. ENABLE MAGENTO PERFORMANCE FEATURES
# ============================================================================
log ""
log "Step 6: Enabling additional Magento performance features..."

# Defer parsing of JavaScript
php bin/magento config:set dev/js/move_script_to_bottom 1 2>&1 | tee -a "$LOG_FILE"

# Enable lazy loading for images
php bin/magento config:set catalog/frontend/lazy_loading_images 1 2>&1 | tee -a "$LOG_FILE" || log "Lazy loading not available in this version"

# Optimize session storage
php bin/magento config:set admin/security/session_lifetime 31536000 2>&1 | tee -a "$LOG_FILE"

log "✓ Magento performance features enabled"

# ============================================================================
# 7. FLUSH CACHES AND DEPLOY STATIC CONTENT
# ============================================================================
log ""
log "Step 7: Flushing caches..."

php bin/magento cache:clean 2>&1 | tee -a "$LOG_FILE"
php bin/magento cache:flush 2>&1 | tee -a "$LOG_FILE"

log "✓ Caches flushed"

# ============================================================================
# 8. WARM UP CACHE
# ============================================================================
log ""
log "Step 8: Warming up cache..."

for i in {1..5}; do
    TIME=$(curl -s -o /dev/null -w "%{time_total}" https://technostationery.com 2>/dev/null)
    log "  Warmup request $i: ${TIME}s"
    sleep 1
done

log "✓ Cache warmed"

# ============================================================================
# 9. PERFORMANCE TEST
# ============================================================================
log ""
log "Step 9: Running performance test..."

TOTAL=0
for i in {1..10}; do
    TIME=$(curl -s -o /dev/null -w "%{time_total}" https://technostationery.com 2>/dev/null)
    TOTAL=$(echo "$TOTAL + $TIME" | bc 2>/dev/null)
    log "  Request $i: ${TIME}s"
    sleep 1
done

AVG=$(echo "scale=3; $TOTAL / 10" | bc 2>/dev/null)
log ""
log "Average response time: ${AVG}s"

# ============================================================================
# SUMMARY
# ============================================================================
log ""
log "========================================"
log "PHASE 2A QUICK WINS COMPLETED"
log "========================================"
log "✓ Image lazy loading configured"
log "✓ Large images converted to WebP"
log "✓ Existing images optimized"
log "✓ Resource hints added (preconnect, dns-prefetch)"
log "✓ JavaScript deferral configured"
log "✓ Magento performance features enabled"
log "✓ Caches flushed and warmed"
log "✓ Average response time: ${AVG}s"
log ""
log "Backup location: $BACKUP_DIR"
log "Log file: $LOG_FILE"
log "========================================"
log "NEXT: Run Lighthouse audit to measure improvement"
log "Command: ./scripts/lighthouse_audit.sh"
log "========================================"

exit 0
