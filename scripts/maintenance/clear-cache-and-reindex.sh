#!/bin/bash

# Clear Cache and Reindex Script
# This script helps clear Magento cache and reindex after manual image uploads

echo "🧹 Clearing Magento Cache and Reindexing"
echo "======================================"

# Navigate to Magento root directory
cd /home/technadminy7/public_html

# Check if Magento CLI exists
if [ ! -f "bin/magento" ]; then
    echo "❌ Error: Magento CLI not found at bin/magento"
    exit 1
fi

# Clear cache
echo "🔄 Clearing cache..."
php bin/magento cache:flush

if [ $? -eq 0 ]; then
    echo "✅ Cache cleared successfully"
else
    echo "❌ Error clearing cache"
    exit 1
fi

# Reindex
echo "🔄 Reindexing..."
php bin/magento indexer:reindex

if [ $? -eq 0 ]; then
    echo "✅ Reindexing completed successfully"
else
    echo "❌ Error during reindexing"
    exit 1
fi

echo "🎉 Cache clearing and reindexing completed!"
echo ""
echo "📝 Next steps:"
echo "   1. Check your storefront to verify images are displaying correctly"
echo "   2. Review any remaining missing images in the reports"
echo "   3. Run this script again after any additional image uploads"