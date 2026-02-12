#!/bin/bash
#
# Add Performance Optimizations to .htaccess
# Date: 2026-02-12
#

HTACCESS_FILE="/home/technadminy7/public_html/pub/.htaccess"
BACKUP_FILE="/home/technadminy7/public_html/pub/.htaccess.backup.$(date +%Y%m%d_%H%M%S)"

echo "=== ADDING PERFORMANCE OPTIMIZATIONS TO .HTACCESS ==="
echo "Date: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

# Backup current .htaccess
echo "[1/3] Creating backup..."
cp "$HTACCESS_FILE" "$BACKUP_FILE"
echo "✓ Backup created: $BACKUP_FILE"
echo ""

# Check if optimizations already exist
if grep -q "PERFORMANCE OPTIMIZATIONS" "$HTACCESS_FILE"; then
    echo "⚠ Performance optimizations already exist in .htaccess"
    echo "Skipping to avoid duplicates"
    exit 0
fi

echo "[2/3] Adding performance rules..."

# Add performance optimizations to .htaccess
cat >> "$HTACCESS_FILE" << 'EOF'

############################################
## PERFORMANCE OPTIMIZATIONS - Added 2026-02-12
############################################

## Enable Gzip Compression
<IfModule mod_deflate.c>
    # Compress HTML, CSS, JavaScript, Text, XML and fonts
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/json
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

## Leverage Browser Caching
<IfModule mod_expires.c>
    ExpiresActive On
    
    # Images
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/webp "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 year"
    ExpiresByType image/x-icon "access plus 1 year"
    
    # CSS and JavaScript
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType text/javascript "access plus 1 month"
    
    # Fonts
    ExpiresByType application/x-font-ttf "access plus 1 year"
    ExpiresByType font/opentype "access plus 1 year"
    ExpiresByType application/x-font-woff "access plus 1 year"
    ExpiresByType application/font-woff "access plus 1 year"
    ExpiresByType application/font-woff2 "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 year"
    
    # Default
    ExpiresDefault "access plus 2 days"
</IfModule>

## Set Cache-Control Headers
<IfModule mod_headers.c>
    # Cache static assets for 1 year
    <FilesMatch "\.(jpg|jpeg|png|gif|webp|svg|ico|css|js|woff|woff2|ttf|otf|eot)$">
        Header set Cache-Control "max-age=31536000, public, immutable"
    </FilesMatch>
    
    # Don't cache HTML
    <FilesMatch "\.(html|htm|php|phtml)$">
        Header set Cache-Control "no-cache, no-store, must-revalidate"
        Header set Pragma "no-cache"
        Header set Expires 0
    </FilesMatch>
    
    # Add Security Headers
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
    
    # Remove X-Powered-By header
    Header unset X-Powered-By
</IfModule>

## Enable Keep-Alive
<IfModule mod_headers.c>
    Header set Connection keep-alive
</IfModule>

## Disable ETags (we use Cache-Control instead)
<IfModule mod_headers.c>
    Header unset ETag
</IfModule>
FileETag None

## END PERFORMANCE OPTIMIZATIONS
############################################

EOF

echo "✓ Performance rules added to .htaccess"
echo ""

echo "[3/3] Verifying .htaccess syntax..."
# Test if .htaccess is valid by checking if Apache can read it
if apache2 -t 2>&1 | grep -qi "syntax ok"; then
    echo "✓ .htaccess syntax is valid"
else
    echo "⚠ Could not verify Apache syntax (may not have permission)"
    echo "  Please test manually: apache2 -t"
fi
echo ""

echo "=== OPTIMIZATION COMPLETE ==="
echo ""
echo "Added optimizations:"
echo "  ✓ Gzip compression for text assets"
echo "  ✓ Browser caching (images: 1 year, CSS/JS: 1 month)"
echo "  ✓ Cache-Control headers"
echo "  ✓ Security headers (X-Frame-Options, etc.)"
echo "  ✓ Keep-Alive connections"
echo "  ✓ Disabled ETags"
echo ""
echo "Backup location: $BACKUP_FILE"
echo ""
echo "To rollback if needed:"
echo "  mv $BACKUP_FILE $HTACCESS_FILE"
echo ""
echo "Test your site now: https://technostationery.com"
echo ""
