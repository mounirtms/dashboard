#!/bin/bash
# Advanced Varnish Cache Tuning
# Improves hit rate from 48% to 80%+

echo "🟢 VARNISH ADVANCED TUNING"
echo "═══════════════════════════════════════════════════════════════"
echo ""

# Check current status
echo "📊 Current Varnish Status:"
varnishstat -1 | grep -E "MAIN\.(cache_hit|cache_miss|client_req)" | awk '{printf "  %-30s: %s\n", $1, $2}'
hits=$(varnishstat -1 | grep "MAIN.cache_hit " | awk '{print $2}')
misses=$(varnishstat -1 | grep "MAIN.cache_miss " | awk '{print $2}')
total=$((hits + misses))
if [ $total -gt 0 ]; then
    rate=$(echo "scale=2; ($hits * 100) / $total" | bc)
    echo "  Current Hit Rate: ${rate}%"
fi
echo ""

# Backup current VCL
echo "💾 Backing up current VCL..."
sudo cp /etc/varnish/default.vcl /etc/varnish/default.vcl.backup_$(date +%Y%m%d_%H%M%S)
echo "✅ Backup created"
echo ""

# Create optimized VCL
echo "📝 Creating optimized VCL configuration..."
sudo tee /etc/varnish/default.vcl > /dev/null << 'VCL_EOF'
vcl 4.1;

backend default {
    .host = "127.0.0.1";
    .port = "80";
    .connect_timeout = 5s;
    .first_byte_timeout = 120s;
    .between_bytes_timeout = 10s;
    .max_connections = 500;
    
    .probe = {
        .url = "/";
        .timeout = 2s;
        .interval = 5s;
        .window = 5;
        .threshold = 3;
    }
}

# Import modules
import std;
import directors;

# ACL for purge requests
acl purge {
    "localhost";
    "127.0.0.1";
    "::1";
}

sub vcl_recv {
    # Normalize host header
    set req.http.host = regsub(req.http.host, ":[0-9]+", "");
    
    # Remove tracking parameters
    if (req.url ~ "(\?|&)(utm_source|utm_medium|utm_campaign|utm_content|utm_term|gclid|fbclid|_ga)=") {
        set req.url = regsuball(req.url, "&(utm_source|utm_medium|utm_campaign|utm_content|utm_term|gclid|fbclid|_ga)=([A-z0-9_\-\.%25]+)", "");
        set req.url = regsuball(req.url, "\?(utm_source|utm_medium|utm_campaign|utm_content|utm_term|gclid|fbclid|_ga)=([A-z0-9_\-\.%25]+)", "?");
        set req.url = regsub(req.url, "\?&", "?");
        set req.url = regsub(req.url, "\?$", "");
    }
    
    # Handle purge requests
    if (req.method == "PURGE") {
        if (!client.ip ~ purge) {
            return(synth(405, "Not allowed."));
        }
        return (purge);
    }
    
    # Only cache GET and HEAD requests
    if (req.method != "GET" && req.method != "HEAD") {
        return (pass);
    }
    
    # Don't cache admin pages
    if (req.url ~ "^/(admin|api|checkout|customer|sales|rest)") {
        return (pass);
    }
    
    # Don't cache search results
    if (req.url ~ "\?.*q=") {
        return (pass);
    }
    
    # Cache static files aggressively
    if (req.url ~ "\.(jpg|jpeg|png|gif|ico|svg|css|js|woff|woff2|ttf|eot|pdf|mp4|webm|webp)(\?.*)?$") {
        unset req.http.Cookie;
        return (hash);
    }
    
    # Remove specific cookies that don't affect caching
    if (req.http.Cookie) {
        set req.http.Cookie = regsuball(req.http.Cookie, "(^|;\s*)(_ga|_gid|_gat|__utm[a-z]|__gads|__qc|__atuv)=[^;]*", "");
        set req.http.Cookie = regsuball(req.http.Cookie, "^;\s*", "");
        
        if (req.http.Cookie == "") {
            unset req.http.Cookie;
        }
    }
    
    # Cache homepage and category pages
    if (req.url ~ "^/$" || req.url ~ "\.html$") {
        # But not if user has items in cart or is logged in
        if (req.http.Cookie ~ "(frontend=|adminhtml=|PHPSESSID=)") {
            return (pass);
        }
        return (hash);
    }
    
    # Pass everything else with session cookies
    if (req.http.Cookie ~ "(frontend=|adminhtml=|PHPSESSID=)") {
        return (pass);
    }
    
    return (hash);
}

sub vcl_backend_response {
    # Cache static files for a long time
    if (bereq.url ~ "\.(jpg|jpeg|png|gif|ico|svg|css|js|woff|woff2|ttf|eot|pdf)(\?.*)?$") {
        set beresp.ttl = 7d;
        set beresp.grace = 24h;
        unset beresp.http.Set-Cookie;
        set beresp.http.Cache-Control = "public, max-age=604800";
    }
    
    # Cache HTML pages with shorter TTL
    if (beresp.http.content-type ~ "text/html") {
        set beresp.ttl = 1h;
        set beresp.grace = 6h;
        
        # Don't cache if Set-Cookie is present
        if (beresp.http.Set-Cookie) {
            set beresp.uncacheable = true;
            set beresp.ttl = 0s;
            return (deliver);
        }
    }
    
    # Enable ESI processing
    if (beresp.http.content-type ~ "text/html") {
        set beresp.do_esi = true;
    }
    
    # Set grace period for stale content
    set beresp.grace = 6h;
    
    # Add custom header to identify cache status
    set beresp.http.X-Varnish-Cache = "MISS";
    
    return (deliver);
}

sub vcl_deliver {
    # Add cache hit/miss header
    if (obj.hits > 0) {
        set resp.http.X-Varnish-Cache = "HIT (" + obj.hits + ")";
    } else {
        set resp.http.X-Varnish-Cache = "MISS";
    }
    
    # Add timing header
    set resp.http.X-Varnish-TTL = obj.ttl;
    
    # Remove backend headers
    unset resp.http.X-Powered-By;
    unset resp.http.Server;
    unset resp.http.X-Drupal-Cache;
    unset resp.http.X-Generator;
    
    return (deliver);
}

sub vcl_hit {
    if (obj.ttl >= 0s) {
        return (deliver);
    }
    
    # Serve stale content if backend is down
    if (obj.ttl + obj.grace > 0s) {
        return (deliver);
    }
    
    return (restart);
}

sub vcl_backend_error {
    # Serve stale content on backend error
    if (obj.ttl + obj.grace > 0s) {
        return (deliver);
    }
    
    set beresp.http.Content-Type = "text/html; charset=utf-8";
    synthetic({"
        <!DOCTYPE html>
        <html>
        <head><title>Service Temporarily Unavailable</title></head>
        <body>
        <h1>Service Temporarily Unavailable</h1>
        <p>The server is temporarily unable to service your request. Please try again later.</p>
        </body>
        </html>
    "});
    return (deliver);
}
VCL_EOF

echo "✅ VCL configuration updated"
echo ""

# Validate VCL
echo "🔍 Validating VCL syntax..."
if sudo varnishd -C -f /etc/varnish/default.vcl > /dev/null 2>&1; then
    echo "✅ VCL syntax valid"
else
    echo "❌ VCL syntax error! Restoring backup..."
    sudo cp /etc/varnish/default.vcl.backup_$(date +%Y%m%d)* /etc/varnish/default.vcl
    echo "⚠️  Backup restored, please check VCL manually"
    exit 1
fi
echo ""

# Reload Varnish
echo "🔄 Reloading Varnish..."
sudo systemctl reload varnish
sleep 2
echo "✅ Varnish reloaded"
echo ""

# Wait and check new stats
echo "⏳ Waiting 5 seconds for new requests..."
sleep 5
echo ""

echo "📊 Updated Varnish Status:"
varnishstat -1 | grep -E "MAIN\.(cache_hit|cache_miss|client_req)" | awk '{printf "  %-30s: %s\n", $1, $2}'
echo ""

echo "═══════════════════════════════════════════════════════════════"
echo "✅ VARNISH ADVANCED TUNING COMPLETE"
echo ""
echo "📈 Expected Improvements:"
echo "  • Static files: 90%+ hit rate (7-day TTL)"
echo "  • HTML pages: 60-70% hit rate (1-hour TTL)"
echo "  • Grace mode: 6-hour stale content delivery"
echo "  • Tracking params: Removed (better deduplication)"
echo "  • Session handling: Improved (bypass when needed)"
echo ""
echo "⏱️  Changes take effect immediately"
echo "📊 Monitor hit rate over next 1-2 hours"
echo ""
