#!/bin/bash
# post-build.sh v3 — TechnoStationery Dashboard Deploy
# Vite builds to /tmp/dashboard-build/ then this copies to public_html/build/
# with correct MIME types via build/.htaccess + SW cache-busting stamp injection

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
PUBLIC_HTML="$(cd "$PROJECT_DIR/../../public_html" && pwd)"
BUILD_DIR="$PUBLIC_HTML/build"
ROOT_HTML="$PUBLIC_HTML/index.html"
TMP_BUILD="/tmp/dashboard-build"
TMP_ASSETS="$TMP_BUILD/assets"

echo "=== post-build.sh v3 ==="
echo "  Temp     : $TMP_BUILD"
echo "  Target   : $BUILD_DIR"

[ ! -d "$TMP_ASSETS" ] && echo "ERROR: $TMP_ASSETS missing" && exit 1

mkdir -p "$BUILD_DIR/assets"

# Deploy assets
cp "$TMP_ASSETS"/* "$BUILD_DIR/assets/"
for f in favicon.svg icons.svg; do
  [ -f "$TMP_BUILD/$f" ] && cp "$TMP_BUILD/$f" "$BUILD_DIR/$f"
done

# Deploy MIME-fix .htaccess — sets application/javascript, disables rewrites,
# and enables long-term immutable caching for hashed assets
cat > "$BUILD_DIR/.htaccess" << 'HTEOF'
# /build/.htaccess — Static SPA assets — NO PHP, correct MIME types
RemoveHandler .html .htm
RemoveType    .html .htm
<FilesMatch "\.html?$">
    SetHandler default-handler
</FilesMatch>
AddType application/javascript  .js .mjs
AddType text/css                .css
AddType image/png               .png
AddType image/svg+xml           .svg
AddType image/x-icon            .ico
Options -Indexes
<IfModule mod_rewrite.c>
    RewriteEngine Off
</IfModule>
<FilesMatch "\.(js|css|png|svg|ico|woff2?)$">
    Header always set Cache-Control "public, max-age=31536000, immutable"
</FilesMatch>
AddDefaultCharset UTF-8
HTEOF
echo "  build/.htaccess written"

# Inject SW cache-busting build stamp into index.html
# Stamp = unix timestamp of this deploy (changes every build)
BUILD_STAMP="$(date +%s)"
TMP_IDX="$TMP_BUILD/index.html"

# Replace __BUILD_STAMP__ placeholder with actual timestamp
sed "s/__BUILD_STAMP__/$BUILD_STAMP/g" "$TMP_IDX" > "$ROOT_HTML"
echo "  index.html deployed (BUILD_STAMP=$BUILD_STAMP)"

# Remove stale index hash files from previous builds
NEW_IDX=$(ls "$TMP_ASSETS"/index-*.js 2>/dev/null | head -1 | xargs basename 2>/dev/null)
if [ -n "$NEW_IDX" ]; then
  for f in "$BUILD_DIR/assets"/index-*.js; do
    [ -f "$f" ] && [ "$(basename $f)" != "$NEW_IDX" ] && rm -f "$f" && echo "  removed stale $(basename $f)"
  done
fi

# Remove stale vendor chunks (hash changed between builds)
for f in "$BUILD_DIR/assets"/vendor-*.js; do
  [ -f "$f" ] || continue
  base="$(basename $f)"
  [ ! -f "$TMP_ASSETS/$base" ] && rm -f "$f" && echo "  removed stale $base"
done

JS=$(find "$BUILD_DIR/assets/" -name '*.js' | wc -l)
echo ""
echo "Done: $JS JS files in $BUILD_DIR/assets/"
echo "  SW cache-bust stamp: $BUILD_STAMP"
echo "Live: https://dashboard.technostationery.com"
