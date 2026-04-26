#!/bin/bash
###############################################################################
# Aggressive Performance Boost Script
# Purpose: Implement immediate performance optimizations via htaccess
# Date: April 26, 2026
###############################################################################

set -euo pipefail

MAGENTO_ROOT="/home/technadminy7/public_html"
HTACCESS="$MAGENTO_ROOT/.htaccess"
BACKUP="$HTACCESS.backup_aggressive_$(date +%Y%m%d_%H%M%S)"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

log "========================================"
log "AGGRESSIVE PERFORMANCE BOOST"
log "========================================"

cd "$MAGENTO_ROOT"

# Backup htaccess
cp "$HTACCESS" "$BACKUP"
log "✓ Backed up .htaccess to $BACKUP"

# ============================================================================
# ADD ADVANCED PERFORMANCE OPTIMIZATIONS TO .HTACCESS
# ============================================================================

cat >> "$HTACCESS" << 'EOF'

############################################
# AGGRESSIVE PERFORMANCE OPTIMIZATIONS
# Added: April 26, 2026
############################################

<IfModule mod_headers.c>
    # Preload critical resources
    <FilesMatch "\.(html|php)$">
        # Preconnect to external domains
        Header add Link "<https://fonts.googleapis.com>; rel=preconnect; crossorigin" "expr=%{CONTENT_TYPE} == 'text/html'"
        Header add Link "<https://fonts.gstatic.com>; rel=preconnect; crossorigin" "expr=%{CONTENT_TYPE} == 'text/html'"
        
        # DNS Prefetch
        Header add Link "<https://www.google-analytics.com>; rel=dns-prefetch" "expr=%{CONTENT_TYPE} == 'text/html'"
        Header add Link "<https://www.googletagmanager.com>; rel=dns-prefetch" "expr=%{CONTENT_TYPE} == 'text/html'"
    </FilesMatch>
    
    # Add performance hints
    Header set X-UA-Compatible "IE=edge"
    Header set X-Content-Type-Options "nosniff"
    
    # Enable Keep-Alive
    Header set Connection keep-alive
</IfModule>

# Enable HTTP/2 Server Push (if supported)
<IfModule mod_http2.c>
    H2Push on
    H2PushPriority *                       after
    H2PushPriority text/css                before
    H2PushPriority image/jpeg              after   32
    H2PushPriority image/png               after   32
    H2PushPriority application/javascript  interleaved
</IfModule>

# Optimize ETags
<IfModule mod_headers.c>
    Header unset ETag
</IfModule>
FileETag None

# Far-future expires for versioned assets
<IfModule mod_expires.c>
    <FilesMatch "\.(jpg|jpeg|gif|png|webp|svg|woff2|woff|ttf|eot|js|css)$">
        ExpiresActive On
        ExpiresDefault "access plus 1 year"
        Header append Cache-Control "public, immutable"
    </FilesMatch>
</IfModule>

# Compress everything compressible
<IfModule mod_deflate.c>
    SetOutputFilter DEFLATE
    SetEnvIfNoCase Request_URI \.(?:gif|jpe?g|png|webp)$ no-gzip
    
    # Force compression for mangled headers
    <IfModule mod_setenvif.c>
        <IfModule mod_headers.c>
            SetEnvIfNoCase ^(Accept-EncodXng|X-cept-Encoding|X{15}|~{15}|-{15})$ ^((gzip|deflate)\s*,?\s*)+|[X~-]{4,13}$ HAVE_Accept-Encoding
            RequestHeader append Accept-Encoding "gzip,deflate" env=HAVE_Accept-Encoding
        </IfModule>
    </IfModule>
</IfModule>

# Prevent access to hidden files
<FilesMatch "^\.">
    Order allow,deny
    Deny from all
</FilesMatch>

# Optimize mod_rewrite
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    
    # Force HTTPS (if needed)
    # RewriteCond %{HTTPS} off
    # RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]
    
    # Remove trailing slash (for performance)
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [R=301,L]
</IfModule>

EOF

log "✓ Added advanced performance optimizations to .htaccess"

# ============================================================================
# CREATE CLOUDFLARE CONFIGURATION SCRIPT
# ============================================================================

log ""
log "Creating Cloudflare setup script..."

cat > "$MAGENTO_ROOT/CLOUDFLARE_SETUP.md" << 'EOF'
# Cloudflare CDN Setup Guide
## Expected Impact: +15-25 Lighthouse points

### Step 1: Sign Up and Add Site
1. Go to https://dash.cloudflare.com/sign-up
2. Enter your email and create password
3. Click "Add a Site"
4. Enter: `technostationery.com`
5. Select "Free" plan
6. Click "Continue"

### Step 2: Update Nameservers
Cloudflare will provide you with nameservers like:
- `allen.ns.cloudflare.com`
- `linda.ns.cloudflare.com`

Update your domain's nameservers at your registrar:
1. Log in to your domain registrar (where you bought the domain)
2. Find DNS/Nameserver settings
3. Replace existing nameservers with Cloudflare's
4. Save changes
5. Wait 5-30 minutes for propagation

### Step 3: Cloudflare Optimization Settings

#### Speed Settings:
1. Go to **Speed** → **Optimization**
2. Enable:
   - ✅ Auto Minify: Check HTML, CSS, JavaScript
   - ✅ Brotli compression
   - ✅ Early Hints
   - ✅ Rocket Loader (defer JavaScript)
   - ✅ Mirage (image optimization)

#### Caching Settings:
1. Go to **Caching** → **Configuration**
2. Set Caching Level: **Standard**
3. Browser Cache TTL: **1 year**
4. Enable:
   - ✅ Always Online

#### Page Rules (Free plan: 3 rules):
Create these page rules in order:

**Rule 1: Cache Static Assets**
- URL: `technostationery.com/pub/static/*`
- Settings:
  - Cache Level: Cache Everything
  - Edge Cache TTL: 1 month
  - Browser Cache TTL: 1 year

**Rule 2: Cache Media**
- URL: `technostationery.com/pub/media/*`
- Settings:
  - Cache Level: Cache Everything
  - Edge Cache TTL: 1 month
  - Browser Cache TTL: 1 year

**Rule 3: Bypass Cache for Checkout/Account**
- URL: `technostationery.com/checkout/*`
- Settings:
  - Cache Level: Bypass

#### SSL/TLS Settings:
1. Go to **SSL/TLS**
2. Set encryption mode: **Full (strict)**
3. Enable:
   - ✅ Always Use HTTPS
   - ✅ Automatic HTTPS Rewrites

#### Security Settings:
1. Go to **Security** → **Settings**
2. Security Level: **Medium**
3. Challenge Passage: **30 minutes**
4. Enable:
   - ✅ Browser Integrity Check

### Step 4: Verify Setup
After nameservers propagate:
1. Visit your site: `https://technostationery.com`
2. Check if Cloudflare is active:
   ```bash
   curl -I https://technostationery.com | grep -i "cf-"
   ```
   You should see headers like `cf-cache-status`, `cf-ray`, etc.

### Step 5: Purge Cache
After setup:
1. Go to **Caching** → **Configuration**
2. Click "Purge Everything"
3. Wait 30 seconds
4. Test your site

### Step 6: Run Lighthouse
```bash
cd /home/technadminy7/public_html
./scripts/lighthouse_audit.sh
```

### Expected Results:
- **Before Cloudflare**: Lighthouse 15/100
- **After Cloudflare**: Lighthouse 30-45/100 (+15-30 points)
- **TTFB Improvement**: 3.1s → 1.5-2.0s
- **Asset Load Time**: -40-60% reduction
- **Global Performance**: Much faster for international users

### Additional Optimizations (Optional):
- **Cloudflare Images**: Automatic WebP conversion, lazy loading
- **Argo Smart Routing**: Faster routing (paid, $5/month)
- **Polish**: Automatic image optimization (paid)
- **Zaraz**: Faster third-party script loading

### Troubleshooting:
- **Site not loading**: Check nameserver propagation (up to 48h)
- **Mixed content warnings**: Enable "Automatic HTTPS Rewrites"
- **Assets not loading**: Check page rules, verify cache settings
- **Admin panel issues**: Add bypass rule for `/admin/*`

### Support:
- Cloudflare Docs: https://developers.cloudflare.com/
- Community: https://community.cloudflare.com/
EOF

log "✓ Created Cloudflare setup guide: CLOUDFLARE_SETUP.md"

# ============================================================================
# FLUSH AND TEST
# ============================================================================

log ""
log "Flushing caches..."
php bin/magento cache:flush 2>&1 | tail -5

log ""
log "Testing performance (3 requests)..."
TOTAL=0
for i in {1..3}; do
    TIME=$(curl -s -o /dev/null -w "%{time_total}" https://technostationery.com 2>/dev/null)
    echo "  Request $i: ${TIME}s"
    TOTAL=$(echo "$TOTAL + $TIME" | bc)
    sleep 1
done
AVG=$(echo "scale=3; $TOTAL / 3" | bc)
log "Average response time: ${AVG}s"

# ============================================================================
# SUMMARY
# ============================================================================

log ""
log "========================================"
log "AGGRESSIVE OPTIMIZATIONS COMPLETED"
log "========================================"
log "✓ .htaccess updated with:"
log "  - Preconnect hints"
log "  - DNS prefetch"
log "  - HTTP/2 Server Push (if available)"
log "  - Optimized ETags"
log "  - Enhanced compression"
log "  - Far-future expires"
log "✓ Cloudflare setup guide created"
log "✓ Average response time: ${AVG}s"
log ""
log "NEXT STEPS:"
log "1. Review changes: diff $BACKUP $HTACCESS"
log "2. Test website functionality"
log "3. Set up Cloudflare CDN (see CLOUDFLARE_SETUP.md)"
log "4. Run Lighthouse audit after Cloudflare setup"
log ""
log "Backup: $BACKUP"
log "========================================"

exit 0
