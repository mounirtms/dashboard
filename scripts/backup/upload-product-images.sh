#!/bin/bash

# Script to upload product images folder to iDrive
# Using the same configuration as the existing backup scripts

set -e

# === Configuration from streamlined-backup.sh ===
PROJECT_ROOT="/home/technadminy7/public_html"
DATE=$(date +%F)
DATETIME=$(date +%F-%H-%M-%S)
LOG_FILE="${PROJECT_ROOT}/var/log/product-images-upload.log"

# Create log directory if it doesn't exist
mkdir -p "${PROJECT_ROOT}/var/log"

# === iDrive S3 Configuration ===
AWS_CMD="/usr/local/bin/aws"
export AWS_ACCESS_KEY_ID="prQjrOCZTP1yTOPfYvRl"
export AWS_SECRET_ACCESS_KEY="41VJfqqxpbGse3G1UUTtdCLWUNGhtxnuf5DssWpT"
export AWS_DEFAULT_REGION="us-west-1"

S3_BUCKET="s3://weektechno"
S3_ENDPOINT="https://l0y0.la.idrivee2-27.com"

# === Colors for output ===
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# === Functions ===
die() {
    echo -e "${RED}[$(date '+%Y-%m-%d %H:%M:%S')] ❌ ERROR: $1${NC}" | tee -a "$LOG_FILE"
    exit 1
}

log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')] $1${NC}" | tee -a "$LOG_FILE"
}

success() {
    echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')] ✅ SUCCESS: $1${NC}" | tee -a "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}[$(date '+%Y-%m-%d %H:%M:%S')] ⚠️  WARNING: $1${NC}" | tee -a "$LOG_FILE"
}

# Upload product images to iDrive
upload_product_images() {
    log "Uploading product images folder to iDrive..."
    
    # Check if the product images directory exists
    if [ ! -d "$PROJECT_ROOT/product_images_by_sku" ]; then
        die "Product images directory not found: $PROJECT_ROOT/product_images_by_sku"
    fi
    
    # Check if the zip file exists
    if [ ! -f "$PROJECT_ROOT/product_images_by_sku.zip" ]; then
        die "Product images zip file not found: $PROJECT_ROOT/product_images_by_sku.zip"
    fi
    
    # Upload the zip file to iDrive under a products directory
    log "Uploading product_images_by_sku.zip to iDrive..."
    "$AWS_CMD" s3 cp "$PROJECT_ROOT/product_images_by_sku.zip" "$S3_BUCKET/products/product_images_by_sku_$DATETIME.zip" \
        --endpoint-url "$S3_ENDPOINT" || die "Failed to upload product images to iDrive"
    
    success "Product images uploaded to iDrive: products/product_images_by_sku_$DATETIME.zip"
    
    # Also upload the uncompressed folder
    log "Uploading product_images_by_sku folder to iDrive..."
    "$AWS_CMD" s3 sync "$PROJECT_ROOT/product_images_by_sku" "$S3_BUCKET/products/product_images_by_sku/" \
        --endpoint-url "$S3_ENDPOINT" \
        --delete || die "Failed to upload product images folder to iDrive"
    
    success "Product images folder uploaded to iDrive: products/product_images_by_sku/"
}

# Main function
main() {
    log "=== Starting Product Images Upload to iDrive ==="
    
    # Upload product images
    upload_product_images
    
    success "Product images upload to iDrive completed successfully"
    log "=== Upload Process Finished ==="
}

# Run main function
main "$@"