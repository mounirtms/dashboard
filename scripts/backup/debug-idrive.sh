#!/bin/bash

# Debug IDrive Connection Script

echo "=== Debug IDrive Connection ==="

# Set credentials
export AWS_ACCESS_KEY_ID="prQjrOCZTP1yTOPfYvRl"
export AWS_SECRET_ACCESS_KEY="41VJfqqxpbGse3G1UUTtdCLWUNGhtxnuf5DssWpT"
export AWS_DEFAULT_REGION="us-west-1"

S3_BUCKET="s3://weektechno"
S3_ENDPOINT="https://l0y0.la.idrivee2-27.com"

echo "AWS CLI Version:"
aws --version

echo ""
echo "Testing endpoint connectivity:"
curl -I "$S3_ENDPOINT" 2>&1

echo ""
echo "Attempting to list buckets:"
timeout 30 aws s3 ls --endpoint-url "$S3_ENDPOINT" --debug 2>&1 | head -50

echo ""
echo "=== End Debug ==="