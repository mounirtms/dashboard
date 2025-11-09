#!/bin/bash

# Detailed Backup Check Script
# This script provides detailed information about each backup directory

export AWS_ACCESS_KEY_ID="prQjrOCZTP1yTOPfYvRl"
export AWS_SECRET_ACCESS_KEY="41VJfqqxpbGse3G1UUTtdCLWUNGhtxnuf5DssWpT"
export AWS_DEFAULT_REGION="us-west-1"

S3_BUCKET="s3://weektechno"
S3_ENDPOINT="https://l0y0.la.idrivee2-27.com"

echo "=== Detailed IDrive Backup Check ==="

# Get all backup directories
BACKUP_DIRS=$(aws s3 ls "$S3_BUCKET" --endpoint-url "$S3_ENDPOINT" | grep "PRE" | awk '{print $2}' | sed 's/\/$//' | sort -r)

# Check each backup directory
for BACKUP_DIR in $BACKUP_DIRS; do
    echo ""
    echo "=== Checking backup: $BACKUP_DIR ==="
    
    # List all files in the backup (first 20)
    echo "Files in backup (first 20):"
    aws s3 ls "$S3_BUCKET/$BACKUP_DIR/" --recursive --endpoint-url "$S3_ENDPOINT" | head -20
    
    # If there are more than 20 files, show the total count
    TOTAL_FILES=$(aws s3 ls "$S3_BUCKET/$BACKUP_DIR/" --recursive --endpoint-url "$S3_ENDPOINT" | wc -l)
    if [ "$TOTAL_FILES" -gt 20 ]; then
        echo "... and $((TOTAL_FILES - 20)) more files"
    fi
    
    echo "Total files: $TOTAL_FILES"
done

echo ""
echo "=== End of Detailed Backup Check ==="