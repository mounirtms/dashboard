#!/bin/bash
# Cloudflare Cache Purge Script
# Usage: ./purge-cloudflare.sh [url]
# If no URL provided, purges everything

ZONE_ID="4919ad3406fcabba381edbd543814a68"
API_TOKEN="zflwN_9EYIx_UDQ6tcFQJt-4CJOjMxs5mnNncqVj"

if [ -n "$1" ]; then
    # Purge specific URL
    echo "Purging URL: $1"
    curl -s -X POST "https://api.cloudflare.com/client/v4/zones/$ZONE_ID/purge_cache" \
      -H "Authorization: Bearer $API_TOKEN" \
      -H "Content-Type: application/json" \
      --data "{\"files\":[\"$1\"]}"
else
    # Purge everything
    echo "Purging ALL Cloudflare cache..."
    curl -s -X POST "https://api.cloudflare.com/client/v4/zones/$ZONE_ID/purge_cache" \
      -H "Authorization: Bearer $API_TOKEN" \
      -H "Content-Type: application/json" \
      --data '{"purge_everything": true}'
fi
echo ""
echo "Done."
