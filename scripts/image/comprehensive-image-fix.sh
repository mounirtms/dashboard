#!/bin/bash

# Comprehensive Image Fix Script for Magento
# This script syncs missing media files and fixes image resizing issues

echo "🚀 Starting Comprehensive Image Fix Process..."

# Define directories
SOURCE_MEDIA_DIR="/home/betatechnadminy/pub/media"
DEST_MEDIA_DIR="/home/betapublic_html/pub/media"

# Check if source directory exists
if [ ! -d "$SOURCE_MEDIA_DIR" ]; then
    echo "❌ Source media directory does not exist: $SOURCE_MEDIA_DIR"
    exit 1
fi

# Create destination directory if it doesn't exist
mkdir -p "$DEST_MEDIA_DIR"

# Sync media files with rsync
echo "🔄 Syncing media files from backup..."
rsync -avz --progress --stats --delete \
  --exclude='cache/' \
  --exclude='tmp/' \
  --exclude='*.log' \
  "$SOURCE_MEDIA_DIR/" "$DEST_MEDIA_DIR/"

# Check if rsync was successful
if [ $? -eq 0 ]; then
    echo "✅ Media sync completed successfully!"
else
    echo "❌ Media sync failed!"
    exit 1
fi

# Fix permissions
echo "🔧 Fixing file permissions..."
chown -R technadminy7:technadminy7 "$DEST_MEDIA_DIR"

# Run the PHP script to fix specific missing images
echo "🔍 Running PHP script to fix specific missing images..."
cd /home/betapublic_html
php scripts/fix-missing-product-images.php

# Clear Magento cache
echo "🧹 Clearing Magento cache..."
php bin/magento cache:clean
php bin/magento cache:flush

# Reindex catalog images
echo "🔁 Reindexing catalog images..."
php bin/magento indexer:reindex catalog_images

# Run media gallery sync
echo "🔁 Running media gallery sync..."
php bin/magento media-gallery:sync

echo "✨ Comprehensive image fix process completed!"