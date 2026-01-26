#!/bin/bash
# Redis Cache Cleanup Script
# Clears Magento cache keys when memory usage exceeds 80%
# Located in /home/technadminy7/public_html/scripts/

MEMORY_LIMIT=864028672  # 80% of 1GB (1073741824)

# Get current used memory
USED_MEMORY=$(redis-cli info memory | grep "used_memory:" | cut -d: -f2)

echo "Current memory usage: $USED_MEMORY bytes"

if [ "$USED_MEMORY" -gt "$MEMORY_LIMIT" ]; then
    echo "Memory usage exceeds 80% limit. Cleaning cache..."
    DELETED_KEYS=$(redis-cli EVAL "local keys = redis.call('KEYS', ARGV[1]) for i=1,#keys,5000 do redis.call('DEL', unpack(keys, i, math.min(i+4999, #keys))) end return #keys" 0 "zc:*")
    echo "Deleted $DELETED_KEYS cache keys"
    
    # Get new memory usage
    NEW_MEMORY=$(redis-cli info memory | grep "used_memory:" | cut -d: -f2)
    echo "New memory usage: $NEW_MEMORY bytes"
else
    echo "Memory usage is within acceptable limits"
fi