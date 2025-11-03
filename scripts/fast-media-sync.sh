#!/bin/bash

# Fast Media Sync Script for Magento
# Uses rsync with optimized options for maximum performance

echo "🚀 Starting Fast Media Sync..."

# Define source and destination directories
SOURCE_DIR="/home/technadminy7/pub/media/catalog/product"
DEST_DIR="/home/technadminy7/public_html/pub/media/catalog/product"

# Check if source directory exists
if [ ! -d "$SOURCE_DIR" ]; then
    echo "❌ Source directory does not exist: $SOURCE_DIR"
    exit 1
fi

# Create destination directory if it doesn't exist
mkdir -p "$DEST_DIR"

# Use rsync with optimized options for speed
# -a: archive mode (preserves permissions, timestamps, etc.)
# -v: verbose output
# -z: compress data during transfer
# --progress: show progress
# --stats: show transfer statistics
# --delete: delete files in destination that don't exist in source
# --exclude: exclude cache directories which don't need to be synced
echo "🔄 Syncing media files from $SOURCE_DIR to $DEST_DIR"

rsync -avz --progress --stats --delete \
  --exclude='cache/' \
  --exclude='tmp/' \
  --exclude='*.log' \
  "$SOURCE_DIR/" "$DEST_DIR/"

# Check if rsync was successful
if [ $? -eq 0 ]; then
    echo "✅ Media sync completed successfully!"
    
    # Fix permissions
    echo "🔧 Fixing file permissions..."
    chown -R technadminy7:technadminy7 "$DEST_DIR"
    
    # Clear Magento cache
    echo "🧹 Clearing Magento cache..."
    php /home/technadminy7/public_html/bin/magento cache:clean
    php /home/technadminy7/public_html/bin/magento cache:flush
    
    echo "✨ Fast media sync process completed!"
else
    echo "❌ Media sync failed!"
    exit 1
fi
