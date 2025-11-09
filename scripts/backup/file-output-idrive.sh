#!/bin/bash

# IDrive Check Script with File Output

OUTPUT_FILE="/home/technadminy7/public_html/var/idrive-check-output.txt"

echo "=== IDrive Check with File Output ===" > "$OUTPUT_FILE"

# Set credentials
export AWS_ACCESS_KEY_ID="prQjrOCZTP1yTOPfYvRl"
export AWS_SECRET_ACCESS_KEY="41VJfqqxpbGse3G1UUTtdCLWUNGhtxnuf5DssWpT"
export AWS_DEFAULT_REGION="us-west-1"

S3_BUCKET="s3://weektechno"
S3_ENDPOINT="https://l0y0.la.idrivee2-27.com"

echo "1. Checking if output file is writable" >> "$OUTPUT_FILE"
echo "Output file is writable" >> "$OUTPUT_FILE"

echo "2. AWS CLI Version:" >> "$OUTPUT_FILE"
aws --version >> "$OUTPUT_FILE" 2>&1

echo "" >> "$OUTPUT_FILE"
echo "3. Listing buckets:" >> "$OUTPUT_FILE"
aws s3 ls --endpoint-url "$S3_ENDPOINT" >> "$OUTPUT_FILE" 2>&1

echo "" >> "$OUTPUT_FILE"
echo "4. Listing contents of weektechno bucket:" >> "$OUTPUT_FILE"
aws s3 ls "$S3_BUCKET" --endpoint-url "$S3_ENDPOINT" >> "$OUTPUT_FILE" 2>&1

echo "" >> "$OUTPUT_FILE"
echo "5. Getting backup directories:" >> "$OUTPUT_FILE"
BACKUP_DIRS=$(aws s3 ls "$S3_BUCKET" --endpoint-url "$S3_ENDPOINT" 2>&1 | grep "PRE" | awk '{print $2}' | sed 's/\/$//' | sort -r)
echo "$BACKUP_DIRS" >> "$OUTPUT_FILE"

echo "" >> "$OUTPUT_FILE"
echo "6. Checking each backup directory:" >> "$OUTPUT_FILE"
if [ -n "$BACKUP_DIRS" ]; then
    for BACKUP_DIR in $BACKUP_DIRS; do
        echo "   Backup: $BACKUP_DIR" >> "$OUTPUT_FILE"
        FILE_COUNT=$(aws s3 ls "$S3_BUCKET/$BACKUP_DIR/" --recursive --endpoint-url "$S3_ENDPOINT" 2>&1 | wc -l)
        echo "   Files: $FILE_COUNT" >> "$OUTPUT_FILE"
    done
else
    echo "   No backup directories found" >> "$OUTPUT_FILE"
fi

echo "" >> "$OUTPUT_FILE"
echo "=== End of IDrive Check ===" >> "$OUTPUT_FILE"

echo "Check complete. Output written to $OUTPUT_FILE"