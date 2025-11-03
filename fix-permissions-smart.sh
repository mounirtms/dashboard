#!/bin/bash

# Smart permission fixing script for Magento
# Only changes permissions for files/directories that don't have the correct permissions

echo "Starting smart permission fixing..."

# Fix directory permissions (only for directories that don't have 755)
echo "Fixing directory permissions..."
find pub/static/ -type d -not -perm 755 -print0 | xargs -0 -r chmod 755
find pub/media/ -type d -not -perm 755 -print0 | xargs -0 -r chmod 755
find var/ -type d -not -perm 755 -print0 | xargs -0 -r chmod 755
find generated/ -type d -not -perm 755 -print0 | xargs -0 -r chmod 755

# Fix file permissions (only for files that don't have 644)
echo "Fixing file permissions..."
find pub/static/ -type f -not -perm 644 -print0 | xargs -0 -r chmod 644
find pub/media/ -type f -not -perm 644 -print0 | xargs -0 -r chmod 644
find var/ -type f -not -perm 644 -print0 | xargs -0 -r chmod 644
find generated/ -type f -not -perm 644 -print0 | xargs -0 -r chmod 644

# Fix merged cache files permissions (force set permissions on all merged files)
echo "Fixing merged cache files permissions..."
find pub/static/_cache/merged/ -type f -exec chmod 644 {} \;

echo "Smart permission fixing completed."