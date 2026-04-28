#!/bin/bash
###############################################################################
# Upload Backups to iDrive - Manual Script
# Uploads existing backups from /backup to iDrive S3
###############################################################################

export AWS_ACCESS_KEY_ID="prQjrOCZTP1yTOPfYvRl"
export AWS_SECRET_ACCESS_KEY="41VJfqqxpbGse3G1UUTtdCLWUNGhtxnuf5DssWpT"
export AWS_DEFAULT_REGION="us-west-1"

S3_BUCKET="s3://weektechno"
S3_ENDPOINT="https://l0y0.la.idrivee2-27.com"
AWS_CMD="/usr/local/bin/aws"

echo "=== Upload Backups to iDrive ==="
echo ""

# Check if path provided
if [[ $# -eq 0 ]]; then
    echo "Usage: $0 <backup_path>"
    echo ""
    echo "Examples:"
    echo "  $0 /backup/$(date +%F)/databases"
    echo "  $0 /backup/$(date +%F)/system-configs"
    echo "  $0 /backup/$(date +%F)/accounts/technadminy7.tar.gz"
    echo ""
    
    # Show recent backups
    echo "Recent backups:"
    ls -lh /backup/*/databases/ 2>/dev/null | tail -10
    echo ""
    ls -lh /backup/*/system-configs/ 2>/dev/null | tail -10
    echo ""
    
    read -p "Enter backup path to upload: " BACKUP_PATH
else
    BACKUP_PATH="$1"
fi

if [[ ! -e "$BACKUP_PATH" ]]; then
    echo "ERROR: Path does not exist: $BACKUP_PATH"
    exit 1
fi

DATE=$(date +%F)

if [[ -f "$BACKUP_PATH" ]]; then
    # Single file
    FILENAME=$(basename "$BACKUP_PATH")
    echo "Uploading $FILENAME to s3://weektechno/$DATE/"
    $AWS_CMD s3 cp "$BACKUP_PATH" "$S3_BUCKET/$DATE/" \
        --endpoint-url "$S3_ENDPOINT"
else
    # Directory
    echo "Uploading $BACKUP_PATH to s3://weektechno/$DATE/"
    $AWS_CMD s3 sync "$BACKUP_PATH" "$S3_BUCKET/$DATE/$(basename "$BACKUP_PATH")/" \
        --endpoint-url "$S3_ENDPOINT"
fi

if [[ $? -eq 0 ]]; then
    echo ""
    echo "✓ Upload complete!"
    echo "Location: s3://weektechno/$DATE/"
else
    echo ""
    echo "✗ Upload failed!"
    exit 1
fi
