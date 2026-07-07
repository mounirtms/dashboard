#!/bin/bash
# deploy.sh — One-command build + deploy to public_html
# After running this, the dashboard is live immediately at the production URL.
# No rsync, no intermediate build copy, no permission issues.
set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
PUBLIC_HTML="$(cd "$PROJECT_DIR/../../public_html" && pwd)"

echo "╔══════════════════════════════════════════╗"
echo "║   TechnoStationery Dashboard Deploy      ║"
echo "╚══════════════════════════════════════════╝"
echo ""
echo "  Source : $PROJECT_DIR"
echo "  Target : $PUBLIC_HTML/build/"
echo "  Index  : $PUBLIC_HTML/index.html"
echo ""

cd "$PROJECT_DIR"

echo "▶ Building..."
npm run build

echo ""
echo "▶ Verifying..."
if [ -f "$PUBLIC_HTML/build/assets/index-"*.js 2>/dev/null ] || ls "$PUBLIC_HTML/build/assets/index-"*.js 1>/dev/null 2>&1; then
  echo "  ✓ index-*.js present"
else
  echo "  ✗ WARNING: index-*.js not found in build/assets/"
fi

if grep -q "/build/assets/" "$PUBLIC_HTML/index.html"; then
  echo "  ✓ index.html references /build/assets/"
else
  echo "  ✗ WARNING: index.html may have wrong asset paths"
fi

echo ""
echo "══════════════════════════════════════════"
echo "  ✅ Deploy complete"
echo "  🌐 https://dashboard.technostationery.com"
echo "══════════════════════════════════════════"
