#!/bin/bash

# Check All Backups Script
# This script examines the contents of all backup directories in IDrive

export AWS_ACCESS_KEY_ID="prQjrOCZTP1yTOPfYvRl"
export AWS_SECRET_ACCESS_KEY="41VJfqqxpbGse3G1UUTtdCLWUNGhtxnuf5DssWpT"
export AWS_DEFAULT_REGION="us-west-1"

S3_BUCKET="s3://weektechno"
S3_ENDPOINT="https://l0y0.la.idrivee2-27.com"

echo "=== IDrive Backup Examination ==="

# List all backups
echo "All backups in the bucket:"
aws s3 ls "$S3_BUCKET" --endpoint-url "$S3_ENDPOINT" | grep "PRE" | awk '{print $2}' | sed 's/\/$//' | sort -r

# Get all backup directories
BACKUP_DIRS=$(aws s3 ls "$S3_BUCKET" --endpoint-url "$S3_ENDPOINT" | grep "PRE" | awk '{print $2}' | sed 's/\/$//' | sort -r)

# Check each backup directory
for BACKUP_DIR in $BACKUP_DIRS; do
    echo ""
    echo "=== Checking backup: $BACKUP_DIR ==="
    
    # Count total files in the backup
    TOTAL_FILES=$(aws s3 ls "$S3_BUCKET/$BACKUP_DIR/" --recursive --endpoint-url "$S3_ENDPOINT" | wc -l)
    echo "Total files: $TOTAL_FILES"
    
    # Check for media files (images)
    MEDIA_FILES=$(aws s3 ls "$S3_BUCKET/$BACKUP_DIR/" --recursive --endpoint-url "$S3_ENDPOINT" | grep -E "\.(jpg|jpeg|png|gif|webp)" | wc -l)
    echo "Media files: $MEDIA_FILES"
    
    # Show some media files if they exist
    if [ "$MEDIA_FILES" -gt 0 ]; then
        echo "Sample media files:"
        aws s3 ls "$S3_BUCKET/$BACKUP_DIR/" --recursive --endpoint-url "$S3_ENDPOINT" | grep -E "\.(jpg|jpeg|png|gif|webp)" | head -5
    fi
    
    # Check for Magento media directory
    MAGENTO_MEDIA=$(aws s3 ls "$S3_BUCKET/$BACKUP_DIR/" --recursive --endpoint-url "$S3_ENDPOINT" | grep "pub/media" | wc -l)
    echo "Magento media files: $MAGENTO_MEDIA"
    
    if [ "$MAGENTO_MEDIA" -gt 0 ]; then
        echo "Sample Magento media files:"
        aws s3 ls "$S3_BUCKET/$BACKUP_DIR/" --recursive --endpoint-url "$S3_ENDPOINT" | grep "pub/media" | head -5
    fi
done

echo ""
echo "=== End of Backup Examination ==="