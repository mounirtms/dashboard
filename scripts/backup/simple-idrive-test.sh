#!/bin/bash

# Simple iDrive Test Script
# This script tests the connection to iDrive

export AWS_ACCESS_KEY_ID="prQjrOCZTP1yTOPfYvRl"
export AWS_SECRET_ACCESS_KEY="41VJfqqxpbGse3G1UUTtdCLWUNGhtxnuf5DssWpT"
export AWS_DEFAULT_REGION="us-west-1"

S3_BUCKET="s3://weektechno"
S3_ENDPOINT="https://l0y0.la.idrivee2-27.com"

echo "Testing iDrive connection..."

# Test listing buckets
echo "Listing buckets..."
aws s3 ls --endpoint-url "$S3_ENDPOINT"

# Test listing contents of our bucket
echo "Listing contents of our bucket..."
aws s3 ls "$S3_BUCKET" --endpoint-url "$S3_ENDPOINT"