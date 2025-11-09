#!/bin/bash

# Comprehensive Media Sync Script for Magento
# Syncs missing media files from source to destination
# Handles Amasty feed and other critical directories

echo "🚀 Starting Comprehensive Media Sync..."

# Define source and destination directories
SOURCE_MEDIA_DIR="/home/technadminy7/technadminy/pub/media"
DEST_MEDIA_DIR="/home/technadminy7/public_html/pub/media"

# Check if source directory exists
if [ ! -d "$SOURCE_MEDIA_DIR" ]; then
    echo "❌ Source media directory does not exist: $SOURCE_MEDIA_DIR"
    exit 1
fi

# Create destination directory if it doesn't exist
mkdir -p "$DEST_MEDIA_DIR"

# Function to sync directories with rsync
sync_directory() {
    local src_dir="$1"
    local dest_dir="$2"
    local dir_name="$3"
    
    echo "🔄 Syncing $dir_name..."
    
    # Use rsync with optimized options
    rsync -avz --progress --stats --delete \
      --exclude='cache/' \
      --exclude='tmp/' \
      --exclude='*.log' \
      "$src_dir/" "$dest_dir/"
    
    # Check if rsync was successful
    if [ $? -eq 0 ]; then
        echo "✅ $dir_name sync completed successfully!"
        return 0
    else
        echo "❌ $dir_name sync failed!"
        return 1
    fi
}

# Sync main media directory
echo "🔄 Syncing main media directory..."
rsync -avz --progress --stats --delete \
  --exclude='cache/' \
  --exclude='tmp/' \
  --exclude='*.log' \
  "$SOURCE_MEDIA_DIR/" "$DEST_MEDIA_DIR/"

if [ $? -eq 0 ]; then
    echo "✅ Main media directory sync completed successfully!"
else
    echo "❌ Main media directory sync failed!"
    exit 1
fi

# Sync specific critical directories
echo "🔄 Syncing catalog product images..."
sync_directory "$SOURCE_MEDIA_DIR/catalog/product" "$DEST_MEDIA_DIR/catalog/product" "Catalog Product Images"

echo "🔄 Syncing Amasty directory..."
sync_directory "$SOURCE_MEDIA_DIR/amasty" "$DEST_MEDIA_DIR/amasty" "Amasty Directory"

echo "🔄 Syncing wysiwyg directory..."
sync_directory "$SOURCE_MEDIA_DIR/wysiwyg" "$DEST_MEDIA_DIR/wysiwyg" "WYSIWYG Directory"

# Fix permissions for all synced files
echo "🔧 Fixing file permissions..."
chown -R technadminy7:technadminy7 "$DEST_MEDIA_DIR"

# Clear Magento cache
echo "🧹 Clearing Magento cache..."
cd /home/technadminy7/public_html
php bin/magento cache:clean
php bin/magento cache:flush

# Reindex catalog images
echo "🔁 Reindexing catalog images..."
php bin/magento indexer:reindex catalog_images

# Run media gallery sync
echo "🔁 Running media gallery sync..."
php bin/magento media-gallery:sync

echo "✨ Comprehensive media sync process completed!"