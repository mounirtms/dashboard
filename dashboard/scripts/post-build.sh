#!/bin/bash
# Post-build: copy build/index.html → public_html/index.html
# Build output is already in public_html/build/ (single source of truth).
# Vite generates assets at /assets/... but Apache serves from public_html root,
# so we prefix them with /build/ here for correct production URLs.
set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
# public_html is two levels up: dashboard/ → worktree_fix/ → public_html/
PUBLIC_HTML="$(cd "$PROJECT_DIR/../../public_html" && pwd)"
BUILD_DIR="$PUBLIC_HTML/build"
BUILD_HTML="$BUILD_DIR/index.html"
ROOT_HTML="$PUBLIC_HTML/index.html"

if [ ! -f "$BUILD_HTML" ]; then
  echo "ERROR: $BUILD_HTML not found. Run 'vite build' first."
  exit 1
fi

# Vite writes asset paths as /assets/... (base: '/').
# In production Apache root the build lives at /build/, so rewrite to /build/assets/.
# Also preserve the webpushr snippet that lives in the root index.html.
WEBPUSHR_SNIPPET=""
if [ -f "$ROOT_HTML" ]; then
  # Extract everything between the webpushr markers (keep for re-injection)
  WEBPUSHR_SNIPPET=$(sed -n '/<!-- start webpushr/,/<!-- end webpushr/p' "$ROOT_HTML")
fi

sed -e 's|src="/assets/|src="/build/assets/|g' \
    -e 's|href="/assets/|href="/build/assets/|g' \
    -e 's|href="/favicon\.svg"|href="/build/favicon.svg"|g' \
    "$BUILD_HTML" > "$ROOT_HTML"

JS_COUNT=$(find "$BUILD_DIR/assets/" -name '*.js' 2>/dev/null | wc -l)
CSS_COUNT=$(find "$BUILD_DIR/assets/" -name '*.css' 2>/dev/null | wc -l)
IMG_COUNT=$(find "$BUILD_DIR/assets/" -name '*.png' -o -name '*.svg' 2>/dev/null | wc -l)

echo "Post-build complete:"
echo "  • Build dir  : $BUILD_DIR"
echo "  • Root index : $ROOT_HTML"
echo "  • Assets     : ${JS_COUNT} JS  ${CSS_COUNT} CSS  ${IMG_COUNT} images"
echo "  • Serving at : https://dashboard.technostationery.com"
