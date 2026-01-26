#!/bin/bash

# Sanitization script - removes sensitive data from documentation

echo "Starting sanitization process..."

# Create sanitized directory if it doesn't exist
mkdir -p sanitized_backup

# Define sensitive patterns to remove/replace
declare -A replacements=(
    ["beta_dBT8x12y22"]="magento_user"
    ["technadminy7"]="system_user"
    ["127.0.0.1:3307"]="database_host:port"
    ["/home/technadminy7/public_html"]="/var/www/magento"
)

# Files to sanitize
files=(
    "DEPLOYMENT_COMPLETE.md"
    "DEPLOYMENT_FILES_LIST.txt"
    "DEPLOYMENT_GUIDE.md"
    "ENHANCEMENTS_APPLIED.md"
    "FINAL_DEPLOYMENT_REPORT.md"
    "FINAL_SUCCESS_REPORT.md"
    "LIVE_URLS_TEST.txt"
    "QUICK_START.md"
    "README.md"
)

# Sanitize each file
for file in "${files[@]}"; do
    if [ -f "$file" ]; then
        echo "Sanitizing $file..."
        
        # Create backup
        cp "$file" "sanitized_backup/${file}.bak"
        
        # Apply replacements
        for pattern in "${!replacements[@]}"; do
            replacement="${replacements[$pattern]}"
            sed -i "s|$pattern|$replacement|g" "$file"
        done
        
        echo "  ✓ $file sanitized"
    fi
done

echo ""
echo "Sanitization complete!"
echo "Original files backed up to sanitized_backup/"
