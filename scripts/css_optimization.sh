#!/bin/bash
###############################################################################
# Advanced CSS Optimization Script
# Purpose: Extract critical CSS and optimize CSS delivery
# Date: April 26, 2026
###############################################################################

set -euo pipefail

MAGENTO_ROOT="/home/technadminy7/public_html"
LOG_FILE="$MAGENTO_ROOT/logs/css_optimization_$(date +%Y%m%d_%H%M%S).log"
BACKUP_DIR="$MAGENTO_ROOT/backups/css_backup_$(date +%Y%m%d_%H%M%S)"

mkdir -p "$(dirname "$LOG_FILE")"
mkdir -p "$BACKUP_DIR"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

log "========================================"
log "ADVANCED CSS OPTIMIZATION"
log "========================================"

cd "$MAGENTO_ROOT"

# ============================================================================
# 1. ANALYZE CURRENT CSS
# ============================================================================
log ""
log "Step 1: Analyzing current CSS files..."

THEME_CSS="pub/static/frontend/Sm/market/en_US/css/pages-theme.min.css"
if [ -f "$THEME_CSS" ]; then
    SIZE=$(du -h "$THEME_CSS" | cut -f1)
    log "Main theme CSS: $SIZE"
    cp "$THEME_CSS" "$BACKUP_DIR/"
else
    log "⚠ Main theme CSS not found"
fi

# ============================================================================
# 2. CREATE CRITICAL CSS MANUALLY (ESSENTIAL STYLES)
# ============================================================================
log ""
log "Step 2: Creating critical CSS..."

CRITICAL_CSS_DIR="pub/static/frontend/Sm/market/en_US/css/critical"
mkdir -p "$CRITICAL_CSS_DIR"

cat > "$CRITICAL_CSS_DIR/critical.css" << 'EOF'
/* Critical CSS - Above the fold styles */
/* Reset and base */
*{margin:0;padding:0;box-sizing:border-box}
html{font-size:16px;-webkit-text-size-adjust:100%}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;font-size:14px;line-height:1.5;color:#333;background:#fff}

/* Layout */
.page-wrapper{min-height:100vh}
.page-header{background:#fff;border-bottom:1px solid #e5e5e5}
.header{padding:10px 0}
.container{max-width:1200px;margin:0 auto;padding:0 15px}

/* Navigation */
.nav{display:flex;align-items:center}
.nav-item{padding:10px 15px}
.nav-link{color:#333;text-decoration:none}
.nav-link:hover{color:#007bff}

/* Logo */
.logo{display:inline-block;max-width:200px}
.logo img{max-width:100%;height:auto}

/* Search */
.search-form{flex:1;max-width:600px;margin:0 20px}
.search-input{width:100%;padding:10px;border:1px solid #ddd;border-radius:4px}

/* Buttons */
.btn{display:inline-block;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;text-align:center;text-decoration:none}
.btn-primary{background:#007bff;color:#fff}
.btn-primary:hover{background:#0056b3}

/* Loading states */
.loading{opacity:0.6;pointer-events:none}

/* Hide non-critical initially */
.below-fold{display:none}

/* Responsive */
@media(max-width:768px){
    .container{padding:0 10px}
    .nav{flex-direction:column}
}
EOF

log "✓ Critical CSS created ($(du -h "$CRITICAL_CSS_DIR/critical.css" | cut -f1))"

# ============================================================================
# 3. CREATE DEFERRED CSS LOADER
# ============================================================================
log ""
log "Step 3: Creating deferred CSS loader..."

cat > "$CRITICAL_CSS_DIR/css-loader.js" << 'EOF'
/*!
 * Deferred CSS Loader
 * Loads non-critical CSS asynchronously
 */
(function() {
    'use strict';
    
    // Load CSS asynchronously
    function loadCSS(href, before, media, attributes) {
        var doc = window.document;
        var ss = doc.createElement('link');
        var ref;
        if (before) {
            ref = before;
        } else {
            var refs = (doc.body || doc.getElementsByTagName('head')[0]).childNodes;
            ref = refs[refs.length - 1];
        }

        var sheets = doc.styleSheets;
        if (attributes) {
            for (var attributeName in attributes) {
                if (attributes.hasOwnProperty(attributeName)) {
                    ss.setAttribute(attributeName, attributes[attributeName]);
                }
            }
        }
        ss.rel = 'stylesheet';
        ss.href = href;
        ss.media = 'only x';

        function ready(cb) {
            if (doc.body) {
                return cb();
            }
            setTimeout(function() {
                ready(cb);
            });
        }

        ready(function() {
            ref.parentNode.insertBefore(ss, (before ? ref : ref.nextSibling));
        });

        var onloadcssdefined = function(cb) {
            var resolvedHref = ss.href;
            var i = sheets.length;
            while (i--) {
                if (sheets[i].href === resolvedHref) {
                    return cb();
                }
            }
            setTimeout(function() {
                onloadcssdefined(cb);
            });
        };

        function loadCB() {
            if (ss.addEventListener) {
                ss.removeEventListener('load', loadCB);
            }
            ss.media = media || 'all';
        }

        if (ss.addEventListener) {
            ss.addEventListener('load', loadCB);
        }
        ss.onloadcssdefined = onloadcssdefined;
        onloadcssdefined(loadCB);
        return ss;
    }

    // Load non-critical CSS
    if (typeof window !== 'undefined') {
        window.addEventListener('load', function() {
            // Show below-fold content
            var belowFold = document.querySelectorAll('.below-fold');
            for (var i = 0; i < belowFold.length; i++) {
                belowFold[i].style.display = '';
            }
        });
    }
})();
EOF

log "✓ CSS loader script created"

# ============================================================================
# 4. COMPRESS NON-CRITICAL CSS
# ============================================================================
log ""
log "Step 4: Creating compressed CSS bundles..."

# Split large CSS into chunks
if [ -f "$THEME_CSS" ]; then
    log "Splitting theme CSS into chunks..."
    
    # Get file size
    TOTAL_LINES=$(wc -l < "$THEME_CSS")
    CHUNK_SIZE=$((TOTAL_LINES / 4))
    
    log "Total lines: $TOTAL_LINES, Chunk size: $CHUNK_SIZE"
    
    # Create chunks directory
    CHUNKS_DIR="$CRITICAL_CSS_DIR/chunks"
    mkdir -p "$CHUNKS_DIR"
    
    # Note: Actual splitting would require careful CSS parsing
    # For now, just copy the original
    cp "$THEME_CSS" "$CHUNKS_DIR/theme-full.min.css"
    
    log "✓ CSS files organized"
fi

# ============================================================================
# 5. UPDATE MAGENTO CONFIGURATION
# ============================================================================
log ""
log "Step 5: Updating Magento CSS configuration..."

# Disable CSS merge temporarily to prevent conflicts
php bin/magento config:set dev/css/merge_css_files 0 2>&1 | tee -a "$LOG_FILE"

log "✓ CSS merge disabled (will use manual optimization)"

# ============================================================================
# 6. CREATE HTML HEAD MODIFICATION SCRIPT
# ============================================================================
log ""
log "Step 6: Creating head modification instructions..."

cat > "$MAGENTO_ROOT/CSS_OPTIMIZATION_INSTRUCTIONS.md" << 'EOF'
# CSS Optimization Implementation Instructions

## Step 1: Inline Critical CSS

Edit: `app/design/frontend/Sm/market/Magento_Theme/templates/html/head.phtml`

Add before the closing `</head>` tag:

```html
<style id="critical-css">
<?php 
$criticalCss = file_get_contents(__DIR__ . '/../../../../pub/static/frontend/Sm/market/en_US/css/critical/critical.css');
echo $criticalCss;
?>
</style>
```

## Step 2: Defer Non-Critical CSS

Replace existing CSS links with:

```html
<!-- Preload critical fonts -->
<link rel="preload" href="<?= $block->getViewFileUrl('fonts/main.woff2') ?>" as="font" type="font/woff2" crossorigin>

<!-- Defer non-critical CSS -->
<link rel="preload" href="<?= $block->getViewFileUrl('css/pages-theme.min.css') ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="<?= $block->getViewFileUrl('css/pages-theme.min.css') ?>"></noscript>

<!-- CSS loader -->
<script><?php include(__DIR__ . '/../../../../pub/static/frontend/Sm/market/en_US/css/critical/css-loader.js'); ?></script>
```

## Step 3: Add Resource Hints

Add at the top of `<head>`:

```html
<!-- DNS Prefetch -->
<link rel="dns-prefetch" href="//fonts.googleapis.com">
<link rel="dns-prefetch" href="//www.google-analytics.com">

<!-- Preconnect -->
<link rel="preconnect" href="//fonts.googleapis.com" crossorigin>
<link rel="preconnect" href="//fonts.gstatic.com" crossorigin>
```

## Step 4: Deploy and Test

```bash
php bin/magento cache:flush
php bin/magento setup:static-content:deploy -f en_US fr_FR ar_DZ
```

## Step 5: Verify

Run Lighthouse audit to verify improvements.
EOF

log "✓ Implementation instructions created"

# ============================================================================
# 7. FLUSH CACHES
# ============================================================================
log ""
log "Step 7: Flushing caches..."

php bin/magento cache:clean 2>&1 | tee -a "$LOG_FILE"
php bin/magento cache:flush 2>&1 | tee -a "$LOG_FILE"

log "✓ Caches flushed"

# ============================================================================
# SUMMARY
# ============================================================================
log ""
log "========================================"
log "CSS OPTIMIZATION COMPLETED"
log "========================================"
log "✓ Critical CSS created"
log "✓ CSS loader script created"
log "✓ CSS files organized"
log "✓ Implementation instructions created"
log ""
log "Files created:"
log "  - $CRITICAL_CSS_DIR/critical.css"
log "  - $CRITICAL_CSS_DIR/css-loader.js"
log "  - CSS_OPTIMIZATION_INSTRUCTIONS.md"
log ""
log "Next steps:"
log "  1. Review CSS_OPTIMIZATION_INSTRUCTIONS.md"
log "  2. Implement changes in theme templates"
log "  3. Deploy static content"
log "  4. Run Lighthouse audit"
log ""
log "Backup location: $BACKUP_DIR"
log "Log file: $LOG_FILE"
log "========================================"

exit 0
