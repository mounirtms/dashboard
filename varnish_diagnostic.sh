#!/bin/bash
echo "=== VARNISH CONFIGURATION DIAGNOSTIC ==="
echo "Date: $(date)"
echo ""

# Step 1: Check current VCL
echo "Step 1: Current VCL configuration"
echo "Active VCL:"
varnishadm vcl.list 2>&1 | head -10
echo ""

# Step 2: Check Varnish statistics
echo "Step 2: Varnish Cache Statistics"
echo "Cache hits: $(varnishstat -1 | grep 'MAIN.cache_hit ' | awk '{print $2}')"
echo "Cache misses: $(varnishstat -1 | grep 'MAIN.cache_miss ' | awk '{print $2}')"
echo "Objects cached: $(varnishstat -1 | grep 'MAIN.n_object ' | awk '{print $2}')"
echo "Hit rate: $(varnishstat -1 | grep 'MAIN.cache_hit ' | awk '{printf "%.2f%%", ($2/($2+1))*100}')"
echo ""

# Step 3: Find backup VCL files
echo "Step 3: Searching for backup VCL files"
find /home/technadminy7/public_html/backups -name "*.vcl" -o -name "*varnish*" 2>/dev/null | head -20
echo ""
find /etc/varnish -name "*.vcl*" 2>/dev/null
echo ""

# Step 4: Check current default.vcl
echo "Step 4: Current /etc/varnish/default.vcl (first 50 lines)"
head -50 /etc/varnish/default.vcl 2>/dev/null || echo "Cannot read default.vcl"
echo ""

# Step 5: Check backend configuration
echo "Step 5: Backend configuration"
grep -A 10 "backend default" /etc/varnish/default.vcl 2>/dev/null
echo ""

# Step 6: Check if Magento-specific VCL
echo "Step 6: Check for Magento VCL markers"
grep -i "magento\|x-magento\|purge" /etc/varnish/default.vcl 2>/dev/null | head -10
echo ""

# Step 7: Test Varnish response
echo "Step 7: Test Varnish response headers"
curl -I https://technostationery.com/ 2>&1 | grep -E "HTTP|Age|X-Cache|X-Varnish"
echo ""

echo "=== Diagnostic Complete ==="
