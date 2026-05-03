#!/bin/bash
# Cloudflare Page Rules for Better Caching
# Creates page rules to improve cache hit rates

echo "📋 CLOUDFLARE PAGE RULES RECOMMENDATIONS"
echo "═══════════════════════════════════════════════════════════════"
echo ""
echo "⚠️  Page Rules must be created manually in Cloudflare Dashboard"
echo "    (API has limited page rule management)"
echo ""
echo "📍 Recommended Page Rules for Each Zone:"
echo ""

cat << 'RULES'
═══════════════════════════════════════════════════════════════
🎯 RULE 1: Cache Static Assets (Priority: 1)
═══════════════════════════════════════════════════════════════
Pattern: *example.com/*.{jpg,jpeg,png,gif,ico,svg,css,js,woff,woff2,ttf,eot,pdf}

Settings:
  ✓ Cache Level: Cache Everything
  ✓ Edge Cache TTL: 1 month
  ✓ Browser Cache TTL: 1 month

Benefits: Dramatically improves cache hit rate for static files

═══════════════════════════════════════════════════════════════
🎯 RULE 2: Cache Product Images (Priority: 2)
═══════════════════════════════════════════════════════════════
Pattern: *example.com/media/*

Settings:
  ✓ Cache Level: Cache Everything
  ✓ Edge Cache TTL: 7 days
  ✓ Browser Cache TTL: 7 days

Benefits: Caches Magento product images

═══════════════════════════════════════════════════════════════
🎯 RULE 3: Bypass Cache for Admin/API (Priority: 3)
═══════════════════════════════════════════════════════════════
Pattern: *example.com/admin*
Pattern: *example.com/api/*

Settings:
  ✓ Cache Level: Bypass

Benefits: Prevents caching of dynamic admin/API content

═══════════════════════════════════════════════════════════════
🎯 RULE 4: Cache Homepage (Priority: 4)
═══════════════════════════════════════════════════════════════
Pattern: example.com/
Pattern: example.com/index.php

Settings:
  ✓ Cache Level: Cache Everything
  ✓ Edge Cache TTL: 2 hours
  ✓ Browser Cache TTL: 1 hour

Benefits: Caches homepage HTML

═══════════════════════════════════════════════════════════════
🎯 RULE 5: Cache Category Pages (Priority: 5)
═══════════════════════════════════════════════════════════════
Pattern: *example.com/*.html

Settings:
  ✓ Cache Level: Cache Everything
  ✓ Edge Cache TTL: 1 hour
  ✓ Browser Cache TTL: 30 minutes
  ✓ Bypass Cache on Cookie: *cart*

Benefits: Caches category/product pages

═══════════════════════════════════════════════════════════════

📝 IMPLEMENTATION STEPS:

1. Log in to Cloudflare Dashboard
2. Select your zone (e.g., technostationery.com)
3. Go to: Rules → Page Rules
4. Click "Create Page Rule"
5. Add each rule above with specified settings
6. Save and deploy

⚡ EXPECTED RESULTS:

  Before: <1% cache hit rate
  After:  60-80% cache hit rate
  
  • Static assets: 90%+ cache hit
  • Product images: 80%+ cache hit
  • HTML pages: 40-60% cache hit

═══════════════════════════════════════════════════════════════
RULES

echo ""
echo "🔗 Quick Links:"
echo "  techno-dz.org: https://dash.cloudflare.com/e3b5aeeba9328c69ba70333b75f4f01b"
echo "  technostationery.com: https://dash.cloudflare.com/4919ad3406fcabba381edbd543814a68"
echo "  technostationery.com.dz: https://dash.cloudflare.com/8f16ea24eef69603b1d75015ed15c6db"
echo "  tms-algerie.com: https://dash.cloudflare.com/22a67dea9b67bc59a0428524fd822985"
echo "  tmstests.com: https://dash.cloudflare.com/2c7a6870ca56fd2a15a08c031e55639b"
echo ""
