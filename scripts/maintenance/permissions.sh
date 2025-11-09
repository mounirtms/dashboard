#!/bin/bash

# Fix permissions for Magento 2 deployment
echo "Fixing file and directory permissions..."

# Ensure pub/static directory exists
mkdir -p pub/static

# Set proper ownership (replace 'technadminy7' with your actual user if different)
USER="technadminy7"
GROUP="technadminy7"

# Set ownership for the entire project
chown -R $USER:$GROUP .

# Set base permissions
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;

# Set more permissive permissions for directories that need write access
chmod 775 ./var
find ./var -type d -exec chmod 775 {} \;
find ./var -type f -exec chmod 664 {} \;

chmod 775 ./pub/media
find ./pub/media -type d -exec chmod 775 {} \;
find ./pub/media -type f -exec chmod 664 {} \;

chmod 775 ./pub/static
find ./pub/static -type d -exec chmod 775 {} \;
find ./pub/static -type f -exec chmod 664 {} \;

chmod 775 ./generated
find ./generated -type d -exec chmod 775 {} \;
find ./generated -type f -exec chmod 664 {} \;

chmod 775 ./app/etc
find ./app/etc -type f -exec chmod 664 {} \;

# Make sure the Magento CLI is executable
chmod +x bin/magento

echo "Permissions fixed successfully!"