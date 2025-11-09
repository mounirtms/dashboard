#!/bin/bash

# Clean IDrive Check Script
# This script cleanly checks the contents of our IDrive backup

echo "=== Clean IDrive Backup Check ==="

# Set credentials
export AWS_ACCESS_KEY_ID="prQjrOCZTP1yTOPfYvRl"
export AWS_SECRET_ACCESS_KEY="41VJfqqxpbGse3G1UUTtdCLWUNGhtxnuf5DssWpT"
export AWS_DEFAULT_REGION="us-west-1"

S3_BUCKET="s3://weektechno"
S3_ENDPOINT="https://l0y0.la.idrivee2-27.com"

echo "1. Listing all buckets:"
aws s3 ls --endpoint-url "$S3_ENDPOINT" 2>&1

echo ""
echo "2. Listing contents of weektechno bucket:"
aws s3 ls "$S3_BUCKET" --endpoint-url "$S3_ENDPOINT" 2>&1

echo ""
echo "3. Getting backup directories:"
BACKUP_DIRS=$(aws s3 ls "$S3_BUCKET" --endpoint-url "$S3_ENDPOINT" 2>&1 | grep "PRE" | awk '{print $2}' | sed 's/\/$//' | sort -r)
echo "$BACKUP_DIRS"

echo ""
echo "4. Checking each backup directory:"
for BACKUP_DIR in $BACKUP_DIRS; do
    echo "   Backup: $BACKUP_DIR"
    FILE_COUNT=$(aws s3 ls "$S3_BUCKET/$BACKUP_DIR/" --recursive --endpoint-url "$S3_ENDPOINT" 2>&1 | wc -l)
    echo "   Files: $FILE_COUNT"
done

echo ""
echo "=== End of Clean IDrive Check ==="