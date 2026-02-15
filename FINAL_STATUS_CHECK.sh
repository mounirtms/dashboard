#!/bin/bash

echo "═══════════════════════════════════════════════════════════"
echo "🔍 FINAL SITE STATUS CHECK"
echo "═══════════════════════════════════════════════════════════"
echo ""

echo "📊 HTTP Status Checks:"
echo "Homepage:  $(curl -s -o /dev/null -w "HTTP %{http_code}" "https://technostationery.com/")"
echo "Cart:      $(curl -s -o /dev/null -w "HTTP %{http_code}" "https://technostationery.com/checkout/cart/")"
echo "Checkout:  $(curl -s -o /dev/null -w "HTTP %{http_code}" "https://technostationery.com/checkout/")"
echo ""

echo "🔒 Permissions Check:"
ls -ld pub/static/ | awk '{print "pub/static/:  " $1 " " $3 ":" $4}'
ls -ld generated/ | awk '{print "generated/:   " $1 " " $3 ":" $4}'
ls -ld var/ | awk '{print "var/:         " $1 " " $3 ":" $4}'
echo ""

echo "⚙️ Maintenance Mode:"
php bin/magento maintenance:status
echo ""

echo "📦 Amasty Checkout Status:"
php bin/magento config:show amasty_checkout/general/enabled | awk '{print "Database Config: " $0}'
grep "Amasty_Checkout" app/etc/config.php | head -1
echo ""

echo "🌍 French Locale Deployment:"
if [ -d "pub/static/frontend/Sm/market/fr_FR" ]; then
  FILE_COUNT=$(find pub/static/frontend/Sm/market/fr_FR -type f | wc -l)
  echo "✅ French static files deployed: $FILE_COUNT files"
else
  echo "❌ French static files NOT found"
fi
echo ""

echo "📝 Recent Git Commits:"
git log --oneline -5
echo ""

echo "═══════════════════════════════════════════════════════════"
echo "✅ Status Check Complete"
echo "═══════════════════════════════════════════════════════════"
