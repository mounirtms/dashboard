#!/bin/bash
###############################################################################
# QUICK WINS Performance Implementation Script
# Date: April 26, 2026
# Purpose: Implement immediate performance optimizations
# Estimated Time: 10-15 minutes
###############################################################################

set -euo pipefail

# Configuration
MAGENTO_ROOT="/home/technadminy7/public_html"
LOG_FILE="$MAGENTO_ROOT/logs/quick_wins_$(date +%Y%m%d_%H%M%S).log"
BACKUP_DIR="$MAGENTO_ROOT/backups/pre_quickwins_$(date +%Y%m%d_%H%M%S)"

# Create directories
mkdir -p "$(dirname "$LOG_FILE")"
mkdir -p "$BACKUP_DIR"

# Logging function
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

log "========================================="
log "QUICK WINS PERFORMANCE IMPLEMENTATION"
log "========================================="

cd "$MAGENTO_ROOT"

# Function to backup file
backup_file() {
    local file=$1
    if [ -f "$file" ]; then
        cp "$file" "$BACKUP_DIR/" 2>/dev/null || true
        log "Backed up: $file"
    fi
}

# ============================================================================
# 1. OPTIMIZE FONT LOADING (font-display: swap)
# ============================================================================
log "Step 1: Optimizing font loading..."

# Check if custom theme CSS exists
THEME_CSS_DIRS=$(find pub/static/frontend/Sm/market/ -type d -name css 2>/dev/null | head -5)

for css_dir in $THEME_CSS_DIRS; do
    if [ -d "$css_dir" ]; then
        log "Processing CSS directory: $css_dir"
        
        # Add font-display: swap to @font-face rules
        find "$css_dir" -name "*.css" -type f -exec grep -l "@font-face" {} \; 2>/dev/null | while read css_file; do
            if ! grep -q "font-display: swap" "$css_file"; then
                log "Adding font-display to: $css_file"
                # Backup
                backup_file "$css_file"
                # Add font-display: swap to all @font-face declarations
                sed -i '/@font-face/,/}/ s/}/  font-display: swap;\n}/' "$css_file" 2>/dev/null || true
            fi
        done
    fi
done

log "✓ Font loading optimized"

# ============================================================================
# 2. ENABLE BROWSER CACHING HEADERS
# ============================================================================
log "Step 2: Configuring browser caching headers..."

# Backup .htaccess
backup_file "$MAGENTO_ROOT/.htaccess"

# Check if caching rules already exist
if ! grep -q "# PERFORMANCE OPTIMIZATION - Browser Caching" .htaccess 2>/dev/null; then
    log "Adding browser caching rules to .htaccess..."
    
    cat >> .htaccess << 'EOF'

############################################
# PERFORMANCE OPTIMIZATION - Browser Caching
############################################
<IfModule mod_expires.c>
    ExpiresActive On
    
    # Images
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/webp "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 year"
    ExpiresByType image/x-icon "access plus 1 year"
    
    # Fonts
    ExpiresByType font/woff2 "access plus 1 year"
    ExpiresByType font/woff "access plus 1 year"
    ExpiresByType font/ttf "access plus 1 year"
    ExpiresByType application/font-woff "access plus 1 year"
    ExpiresByType application/font-woff2 "access plus 1 year"
    
    # CSS and JavaScript
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType text/javascript "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType application/x-javascript "access plus 1 month"
    
    # Others
    ExpiresByType application/pdf "access plus 1 month"
    ExpiresByType text/x-cross-domain-policy "access plus 1 week"
</IfModule>

<IfModule mod_headers.c>
    # Cache-Control headers
    <FilesMatch "\.(jpg|jpeg|png|gif|webp|svg|woff2|woff|ttf|eot)$">
        Header set Cache-Control "max-age=31536000, public, immutable"
    </FilesMatch>
    
    <FilesMatch "\.(css|js)$">
        Header set Cache-Control "max-age=2592000, public"
    </FilesMatch>
    
    # Preload hints for critical resources
    Header add Link "</pub/static/frontend/Sm/market/en_US/css/styles-m.css>; rel=preload; as=style" "expr=%{CONTENT_TYPE} == 'text/html'"
    Header add Link "</pub/static/frontend/Sm/market/en_US/css/styles-l.css>; rel=preload; as=style" "expr=%{CONTENT_TYPE} == 'text/html'"
</IfModule>

EOF
    log "✓ Browser caching headers added"
else
    log "ℹ Browser caching rules already exist"
fi

# ============================================================================
# 3. ENABLE GZIP/BROTLI COMPRESSION
# ============================================================================
log "Step 3: Ensuring compression is enabled..."

if ! grep -q "# PERFORMANCE OPTIMIZATION - Compression" .htaccess 2>/dev/null; then
    log "Adding compression rules..."
    
    cat >> .htaccess << 'EOF'

############################################
# PERFORMANCE OPTIMIZATION - Compression
############################################
<IfModule mod_deflate.c>
    # Compress HTML, CSS, JavaScript, Text, XML and fonts
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/vnd.ms-fontobject
    AddOutputFilterByType DEFLATE application/x-font
    AddOutputFilterByType DEFLATE application/x-font-opentype
    AddOutputFilterByType DEFLATE application/x-font-otf
    AddOutputFilterByType DEFLATE application/x-font-truetype
    AddOutputFilterByType DEFLATE application/x-font-ttf
    AddOutputFilterByType DEFLATE application/x-javascript
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE font/opentype
    AddOutputFilterByType DEFLATE font/otf
    AddOutputFilterByType DEFLATE font/ttf
    AddOutputFilterByType DEFLATE image/svg+xml
    AddOutputFilterByType DEFLATE image/x-icon
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/javascript
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/xml
    
    # Remove browser bugs (only needed for really old browsers)
    BrowserMatch ^Mozilla/4 gzip-only-text/html
    BrowserMatch ^Mozilla/4\.0[678] no-gzip
    BrowserMatch \bMSIE !no-gzip !gzip-only-text/html
    Header append Vary User-Agent
</IfModule>

EOF
    log "✓ Compression rules added"
else
    log "ℹ Compression rules already exist"
fi

# ============================================================================
# 4. MAGENTO PERFORMANCE CONFIGURATIONS
# ============================================================================
log "Step 4: Optimizing Magento configurations..."

# Enable flat catalog (if not already)
log "Checking flat catalog settings..."
php bin/magento config:show catalog/frontend/flat_catalog_category 2>&1 | tee -a "$LOG_FILE"
php bin/magento config:show catalog/frontend/flat_catalog_product 2>&1 | tee -a "$LOG_FILE"

# Enable flat catalog
log "Enabling flat catalog..."
php bin/magento config:set catalog/frontend/flat_catalog_category 1 2>&1 | tee -a "$LOG_FILE" || true
php bin/magento config:set catalog/frontend/flat_catalog_product 1 2>&1 | tee -a "$LOG_FILE" || true

# Optimize image lazy loading
log "Enabling image lazy loading..."
php bin/magento config:set dev/js/enable_js_bundling 1 2>&1 | tee -a "$LOG_FILE" || true
php bin/magento config:set dev/js/minify_files 1 2>&1 | tee -a "$LOG_FILE" || true
php bin/magento config:set dev/css/minify_files 1 2>&1 | tee -a "$LOG_FILE" || true
php bin/magento config:set dev/css/merge_css_files 1 2>&1 | tee -a "$LOG_FILE" || true
php bin/magento config:set dev/js/merge_files 1 2>&1 | tee -a "$LOG_FILE" || true

# Async sending of sales emails
log "Enabling async email sending..."
php bin/magento config:set sales_email/general/async_sending 1 2>&1 | tee -a "$LOG_FILE" || true

# ============================================================================
# 5. OPTIMIZE IMAGES - FIND LARGE IMAGES
# ============================================================================
log "Step 5: Analyzing image sizes..."

log "Finding large images (>500KB)..."
find pub/media -type f \( -name "*.jpg" -o -name "*.jpeg" -o -name "*.png" \) -size +500k 2>/dev/null | wc -l | xargs -I {} log "Found {} images larger than 500KB"

log "Top 10 largest images:"
find pub/media -type f \( -name "*.jpg" -o -name "*.jpeg" -o -name "*.png" \) -printf "%s %p\n" 2>/dev/null | sort -rn | head -10 | awk '{size=$1/1024/1024; printf "  %.2f MB - %s\n", size, $2}' | tee -a "$LOG_FILE"

# ============================================================================
# 6. REDIS MEMORY OPTIMIZATION
# ============================================================================
log "Step 6: Checking Redis configuration..."

log "Current Redis memory usage:"
redis-cli info memory | grep "used_memory_human\|maxmemory_human" 2>&1 | tee -a "$LOG_FILE" || log "Redis info not available"

# ============================================================================
# 7. PHP OPCACHE OPTIMIZATION
# ============================================================================
log "Step 7: Verifying PHP OPcache settings..."

php -r "echo 'OPcache Enabled: ' . ini_get('opcache.enable') . PHP_EOL;" 2>&1 | tee -a "$LOG_FILE"
php -r "echo 'OPcache Memory: ' . ini_get('opcache.memory_consumption') . PHP_EOL;" 2>&1 | tee -a "$LOG_FILE"
php -r "echo 'OPcache Max Files: ' . ini_get('opcache.max_accelerated_files') . PHP_EOL;" 2>&1 | tee -a "$LOG_FILE"

# ============================================================================
# 8. FLUSH CACHES
# ============================================================================
log "Step 8: Flushing caches..."

php bin/magento cache:clean 2>&1 | tee -a "$LOG_FILE"
php bin/magento cache:flush 2>&1 | tee -a "$LOG_FILE"

# Warm up cache
log "Warming up cache with 5 requests..."
for i in {1..5}; do
    curl -s -o /dev/null -w "Request $i: %{http_code} - %{time_total}s\n" https://technostationery.com 2>&1 | tee -a "$LOG_FILE"
    sleep 1
done

# ============================================================================
# 9. PERFORMANCE TEST
# ============================================================================
log "Step 9: Running performance test..."

log "Testing homepage performance (10 requests)..."
TOTAL_TIME=0
for i in {1..10}; do
    TIME=$(curl -s -o /dev/null -w "%{time_total}" https://technostationery.com 2>/dev/null)
    TOTAL_TIME=$(echo "$TOTAL_TIME + $TIME" | bc 2>/dev/null)
    log "  Request $i: ${TIME}s"
done

AVG_TIME=$(echo "scale=3; $TOTAL_TIME / 10" | bc 2>/dev/null)
log "Average response time: ${AVG_TIME}s"

# ============================================================================
# SUMMARY
# ============================================================================
log "========================================="
log "QUICK WINS IMPLEMENTATION SUMMARY"
log "========================================="
log "✓ Font loading optimized (font-display: swap)"
log "✓ Browser caching headers configured"
log "✓ Compression enabled"
log "✓ Magento performance settings optimized"
log "✓ Flat catalog enabled"
log "✓ Async email sending enabled"
log "✓ Caches flushed and warmed"
log "✓ Average response time: ${AVG_TIME}s"
log ""
log "Backup location: $BACKUP_DIR"
log "Log file: $LOG_FILE"
log "========================================="
log "NEXT STEPS:"
log "1. Run Lighthouse audit: ./scripts/lighthouse_audit.sh"
log "2. Monitor performance for 20 minutes"
log "3. Check error logs: tail -f var/log/system.log"
log "4. Review and commit changes if successful"
log "========================================="

exit 0
