#!/bin/bash
# Varnish Configuration Deployment Script
# Backs up current VCL, deploys Magento 2-aware VCL, and verifies

set -e

VCL_SOURCE="/home/dashboard/public_html/config/optimized_varnish.vcl"
VCL_TARGET="/etc/varnish/default.vcl"
BACKUP="/etc/varnish/default.vcl.backup_$(date +%Y%m%d_%H%M%S)"

echo "=== Varnish VCL Deployment ==="
echo ""

# Check if source exists
if [ ! -f "$VCL_SOURCE" ]; then
    echo "ERROR: Source VCL not found at $VCL_SOURCE"
    exit 1
fi

# Backup current VCL
echo "1. Backing up current VCL to $BACKUP"
cp "$VCL_TARGET" "$BACKUP"

# Test new VCL compiles
echo "2. Testing VCL compilation..."
if varnishd -C -f "$VCL_SOURCE" -a :6081 -T localhost:6082 > /dev/null 2>&1; then
    echo "   VCL compilation: OK"
else
    echo "   ERROR: VCL compilation failed!"
    echo "   Rolling back..."
    cp "$BACKUP" "$VCL_TARGET"
    exit 1
fi

# Deploy
echo "3. Deploying new VCL..."
cp "$VCL_SOURCE" "$VCL_TARGET"

# Reload Varnish (zero-downtime)
echo "4. Reloading Varnish..."
systemctl reload varnish

# Wait for reload
sleep 2

# Verify
echo "5. Verifying Varnish..."
HIT_RATE=$(varnishstat -1 -f cache_hit 2>/dev/null | awk '{print $2}')
MISS_RATE=$(varnishstat -1 -f cache_miss 2>/dev/null | awk '{print $2}')

if [ -n "$HIT_RATE" ] && [ -n "$MISS_RATE" ]; then
    TOTAL=$((HIT_RATE + MISS_RATE))
    if [ $TOTAL -gt 0 ]; then
        RATE=$((HIT_RATE * 100 / TOTAL))
        echo "   Cache hit rate: ${RATE}%"
    fi
fi

echo ""
echo "=== Deployment Complete ==="
echo "Backup: $BACKUP"
echo "To rollback: cp $BACKUP $VCL_TARGET && systemctl reload varnish"
