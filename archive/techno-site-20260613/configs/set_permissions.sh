#!/bin/bash

MAGENTO_ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$MAGENTO_ROOT" || exit 1

echo "Setting Magento 2 permissions in: $MAGENTO_ROOT"

find . -type d \
    -not -path './var/*' \
    -not -path './generated/*' \
    -not -path './pub/static/*' \
    -not -path './pub/media/*' \
    -not -path './node_modules/*' \
    -not -path './.git/*' \
    -exec chmod 755 {} +

find . -type f \
    -not -path './var/*' \
    -not -path './generated/*' \
    -not -path './pub/static/*' \
    -not -path './pub/media/*' \
    -not -path './node_modules/*' \
    -not -path './.git/*' \
    -exec chmod 644 {} +

for dir in var generated pub/static pub/media; do
    [ -d "$dir" ] && find "$dir" -type d -exec chmod 775 {} +
    [ -d "$dir" ] && find "$dir" -type f -exec chmod 664 {} +
done

find app/etc -type d -exec chmod 750 {} +
find app/etc -type f -exec chmod 640 {} +

chmod 755 bin/magento

echo "Done."
