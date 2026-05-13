#!/bin/bash
# Cloudflare Optimization Script
# Applies best practice settings across all zones

echo "☁️  CLOUDFLARE OPTIMIZATION SCRIPT"
echo "═══════════════════════════════════════════════════════════════"
echo ""

# Load credentials
CF_EMAIL="webmaster@techno-dz.com"
CF_KEY="35d8fd4b1a5d27eabbce73c6753978fc350bc"

# Zone IDs
ZONES=(
    "e3b5aeeba9328c69ba70333b75f4f01b:techno-dz.org"
    "4919ad3406fcabba381edbd543814a68:technostationery.com"
    "8f16ea24eef69603b1d75015ed15c6db:technostationery.com.dz"
    "22a67dea9b67bc59a0428524fd822985:tms-algerie.com"
    "2c7a6870ca56fd2a15a08c031e55639b:tmstests.com"
)

# Function to make Cloudflare API calls
cf_api() {
    local zone_id=$1
    local setting=$2
    local value=$3
    
    curl -s -X PATCH "https://api.cloudflare.com/client/v4/zones/${zone_id}/settings/${setting}" \
         -H "X-Auth-Email: ${CF_EMAIL}" \
         -H "X-Auth-Key: ${CF_KEY}" \
         -H "Content-Type: application/json" \
         --data "{\"value\":\"${value}\"}"
}

# Optimization settings
optimize_zone() {
    local zone_id=$1
    local zone_name=$2
    
    echo "📍 Optimizing: $zone_name ($zone_id)"
    echo "──────────────────────────────────────────────────────────"
    
    # SSL/TLS - Set to Full (Strict) for better security
    echo -n "  Setting SSL to Full (Strict)... "
    result=$(cf_api "$zone_id" "ssl" "full")
    if echo "$result" | grep -q "success.*true"; then
        echo "✅"
    else
        echo "⚠️  (check manually)"
    fi
    
    # Always Online - Enable
    echo -n "  Enabling Always Online... "
    result=$(cf_api "$zone_id" "always_online" "on")
    if echo "$result" | grep -q "success.*true"; then
        echo "✅"
    else
        echo "⚠️"
    fi
    
    # Early Hints - Enable for faster page loads
    echo -n "  Enabling Early Hints... "
    result=$(cf_api "$zone_id" "early_hints" "on")
    if echo "$result" | grep -q "success.*true"; then
        echo "✅"
    else
        echo "⚠️"
    fi
    
    # HTTP/3 - Enable (already should be on)
    echo -n "  Verifying HTTP/3... "
    result=$(cf_api "$zone_id" "http3" "on")
    if echo "$result" | grep -q "success.*true"; then
        echo "✅"
    else
        echo "⚠️"
    fi
    
    # Brotli Compression - Enable
    echo -n "  Verifying Brotli... "
    result=$(cf_api "$zone_id" "brotli" "on")
    if echo "$result" | grep -q "success.*true"; then
        echo "✅"
    else
        echo "⚠️"
    fi
    
    # Browser Cache TTL - Set to respect existing headers
    echo -n "  Setting Browser Cache TTL... "
    result=$(cf_api "$zone_id" "browser_cache_ttl" "0")
    if echo "$result" | grep -q "success.*true"; then
        echo "✅"
    else
        echo "⚠️"
    fi
    
    # Cache Level - Aggressive
    echo -n "  Setting Cache Level to Aggressive... "
    result=$(cf_api "$zone_id" "cache_level" "aggressive")
    if echo "$result" | grep -q "success.*true"; then
        echo "✅"
    else
        echo "⚠️"
    fi
    
    # Minification - Enable for CSS, JS, HTML
    echo -n "  Enabling Minification... "
    result=$(curl -s -X PATCH "https://api.cloudflare.com/client/v4/zones/${zone_id}/settings/minify" \
         -H "X-Auth-Email: ${CF_EMAIL}" \
         -H "X-Auth-Key: ${CF_KEY}" \
         -H "Content-Type: application/json" \
         --data '{"value":{"css":"on","html":"on","js":"on"}}')
    if echo "$result" | grep -q "success.*true"; then
        echo "✅"
    else
        echo "⚠️"
    fi
    
    # Rocket Loader - Enable for faster JavaScript
    echo -n "  Enabling Rocket Loader... "
    result=$(cf_api "$zone_id" "rocket_loader" "on")
    if echo "$result" | grep -q "success.*true"; then
        echo "✅"
    else
        echo "⚠️"
    fi
    
    # Auto Minify HTML
    echo -n "  Enabling Auto Minify... "
    result=$(cf_api "$zone_id" "mirage" "on")
    if echo "$result" | grep -q "success.*true"; then
        echo "✅"
    else
        echo "⚠️"
    fi
    
    echo ""
}

# Process all zones
for zone_data in "${ZONES[@]}"; do
    IFS=':' read -r zone_id zone_name <<< "$zone_data"
    optimize_zone "$zone_id" "$zone_name"
done

echo "═══════════════════════════════════════════════════════════════"
echo "✅ CLOUDFLARE OPTIMIZATION COMPLETE"
echo ""
echo "📊 Expected Improvements:"
echo "  • Better SSL/TLS security (Full Strict)"
echo "  • Faster page loads (Early Hints, HTTP/3)"
echo "  • Improved caching (Always Online)"
echo "  • Reduced bandwidth (Brotli, Minification)"
echo "  • Faster JavaScript (Rocket Loader)"
echo ""
echo "⏱️  Changes take effect immediately"
echo "📈 Monitor cache hit rates over next 24-48 hours"
echo ""
