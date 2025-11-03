#!/bin/bash

# Examine Latest Backup Script
# This script examines the contents of the latest backup in IDrive

export AWS_ACCESS_KEY_ID="prQjrOCZTP1yTOPfYvRl"
export AWS_SECRET_ACCESS_KEY="41VJfqqxpbGse3G1UUTtdCLWUNGhtxnuf5DssWpT"
export AWS_DEFAULT_REGION="us-west-1"

S3_BUCKET="s3://weektechno"
S3_ENDPOINT="https://l0y0.la.idrivee2-27.com"

echo "=== IDrive Backup Examination ==="

# List all backups
echo "All backups in the bucket:"
aws s3 ls "$S3_BUCKET" --endpoint-url "$S3_ENDPOINT" | grep "PRE" | awk '{print $2}' | sed 's/\/$//' | sort -r

# Get the latest backup (most recent date)
LATEST_BACKUP=$(aws s3 ls "$S3_BUCKET" --endpoint-url "$S3_ENDPOINT" | grep "PRE" | awk '{print $2}' | sed 's/\/$//' | sort -r | head -1)

echo ""
echo "Latest backup: $LATEST_BACKUP"

# List contents of the latest backup
echo ""
echo "Contents of the latest backup:"
aws s3 ls "$S3_BUCKET/$LATEST_BACKUP/" --recursive --endpoint-url "$S3_ENDPOINT" | head -30

# Count total files in the latest backup
TOTAL_FILES=$(aws s3 ls "$S3_BUCKET/$LATEST_BACKUP/" --recursive --endpoint-url "$S3_ENDPOINT" | wc -l)
echo ""
echo "Total files in latest backup: $TOTAL_FILES"

# Check for media files (images)
echo ""
echo "Media files in the latest backup:"
aws s3 ls "$S3_BUCKET/$LATEST_BACKUP/" --recursive --endpoint-url "$S3_ENDPOINT" | grep -E "\.(jpg|jpeg|png|gif|webp)" | head -10

# Check for Magento media directory
echo ""
echo "Checking for Magento media directory:"
aws s3 ls "$S3_BUCKET/$LATEST_BACKUP/" --recursive --endpoint-url "$S3_ENDPOINT" | grep "pub/media" | head -5

echo ""
echo "=== End of Backup Examination ==="