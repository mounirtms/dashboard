vcl 4.1;

import std;

# Backend definition with health checks
backend default {
    .host = "127.0.0.1";
    .port = "80";
    .connect_timeout = 5s;
    .first_byte_timeout = 60s;
    .between_bytes_timeout = 10s;
    .max_connections = 300;
    
    # Health probe
    .probe = {
        .url = "/health-check";
        .interval = 5s;
        .timeout = 2s;
        .window = 5;
        .threshold = 3;
    }
}

# ACL for purge requests
acl purge {
    "localhost";
    "127.0.0.1";
    "::1";
}

sub vcl_recv {
    # ============================================
    # PURGE SUPPORT
    # ============================================
    if (req.method == "PURGE") {
        if (!client.ip ~ purge) {
            return (synth(405, "Not allowed"));
        }
        return (purge);
    }
    
    # ============================================
    # NORMALIZE REQUEST
    # ============================================
    
    # Only cache GET and HEAD requests
    if (req.method != "GET" && req.method != "HEAD") {
        return (pass);
    }
    
    # Remove marketing/tracking query parameters
    if (req.url ~ "(\?|&)(utm_source|utm_medium|utm_campaign|utm_content|utm_term|gclid|fbclid|msclkid|_ga|_gid|mc_cid|mc_eid)=") {
        set req.url = regsuball(req.url, "&(utm_source|utm_medium|utm_campaign|utm_content|utm_term|gclid|fbclid|msclkid|_ga|_gid|mc_cid|mc_eid)=([A-z0-9_\-\.%25]+)", "");
        set req.url = regsuball(req.url, "\?(utm_source|utm_medium|utm_campaign|utm_content|utm_term|gclid|fbclid|msclkid|_ga|_gid|mc_cid|mc_eid)=([A-z0-9_\-\.%25]+)", "?");
        set req.url = regsub(req.url, "\?&", "?");
        set req.url = regsub(req.url, "\?$", "");
    }
    
    # Normalize Accept-Encoding
    if (req.http.Accept-Encoding) {
        if (req.url ~ "\.(jpg|jpeg|png|gif|gz|tgz|bz2|tbz|mp3|ogg|swf|flv|pdf|ico|woff|woff2|ttf|eot)$") {
            unset req.http.Accept-Encoding;
        } elsif (req.http.Accept-Encoding ~ "gzip") {
            set req.http.Accept-Encoding = "gzip";
        } elsif (req.http.Accept-Encoding ~ "deflate") {
            set req.http.Accept-Encoding = "deflate";
        } else {
            unset req.http.Accept-Encoding;
        }
    }

    # ============================================
    # DEVICE DETECTION
    # ============================================
    set req.http.X-Device = "desktop";
    if (req.http.User-Agent ~ "(?i)mobile|android|iphone|ipad|ipod|blackberry|opera mini|iemobile") {
        set req.http.X-Device = "mobile";
    }
    
    # ============================================
    # DASHBOARD CACHE POLICY
    # ============================================
    
    # Never cache authenticated/dynamic routes
    if (req.url ~ "^/(user|api|admin|login|logout|checkout|cart|customer|account)/") {
        return (pass);
    }
    
    # Never cache if session cookies present
    if (req.http.Cookie ~ "(frontend=|adminhtml=|PHPSESSID=|wordpress_logged_in|wp-postpass)") {
        return (pass);
    }
    
    # Remove Google Analytics and other tracking cookies
    if (req.http.Cookie) {
        set req.http.Cookie = regsuball(req.http.Cookie, "(^|;\s*)(_ga|_gat|_gid|__utm[a-z])=[^;]*", "");
        set req.http.Cookie = regsuball(req.http.Cookie, "(^|;\s*)(__utm[a-z]+)=[^;]*", "");
        set req.http.Cookie = regsuball(req.http.Cookie, "^;\s*", "");
        
        if (req.http.Cookie == "") {
            unset req.http.Cookie;
        }
    }
    
    # ============================================
    # STATIC ASSETS - ALWAYS CACHE
    # ============================================
    
    # Cache static file extensions
    if (req.url ~ "^[^?]*\.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot|otf|pdf|doc|docx|xls|xlsx|ppt|pptx|zip|gz|mp3|mp4|avi|mov|wmv|flv|swf|webp|bmp|tiff)(\?.*)?$") {
        unset req.http.Cookie;
        return (hash);
    }
    
    # Cache static directories
    if (req.url ~ "^/(static|assets|media|images|css|js|fonts|uploads|files)/") {
        unset req.http.Cookie;
        return (hash);
    }
    
    return (hash);
}

sub vcl_backend_response {
    # ============================================
    # TTL CONFIGURATION
    # ============================================
    
    # Very long TTL for images and fonts (7 days)
    if (bereq.url ~ "\.(jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot|otf|webp|bmp)(\?.*)?$") {
        set beresp.ttl = 7d;
        set beresp.grace = 24h;
        unset beresp.http.Set-Cookie;
    }
    
    # Long TTL for CSS and JS (1 hour)
    if (bereq.url ~ "\.(css|js)(\?.*)?$") {
        set beresp.ttl = 1h;
        set beresp.grace = 24h;
        unset beresp.http.Set-Cookie;
    }
    
    # Medium TTL for documents (30 minutes)
    if (bereq.url ~ "\.(pdf|doc|docx|xls|xlsx|ppt|pptx|zip)(\?.*)?$") {
        set beresp.ttl = 30m;
        set beresp.grace = 6h;
        unset beresp.http.Set-Cookie;
    }
    
    # Static directories - 1 hour TTL
    if (bereq.url ~ "^/(static|assets|media|images|css|js|fonts)/") {
        set beresp.ttl = 1h;
        set beresp.grace = 24h;
        unset beresp.http.Set-Cookie;
    }
    
    # Default TTL for cacheable content
    if (beresp.ttl <= 0s) {
        set beresp.ttl = 2m;
        set beresp.grace = 1h;
    }
    
    # Don't cache if Set-Cookie is present (except for static assets)
    if (beresp.http.Set-Cookie && bereq.url !~ "\.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot)(\?.*)?$") {
        set beresp.uncacheable = true;
        return (deliver);
    }
    
    # Enable grace mode for all cached content
    set beresp.grace = 24h;
    
    # Enable ESI for HTML content
    if (beresp.http.Content-Type ~ "text/html") {
        set beresp.do_esi = true;
    }
    
    # Remove unnecessary headers
    unset beresp.http.Server;
    unset beresp.http.X-Powered-By;
    
    return (deliver);
}

sub vcl_deliver {
    # Add cache debugging headers
    if (obj.hits > 0) {
        set resp.http.X-Cache = "HIT";
        set resp.http.X-Cache-Hits = obj.hits;
    } else {
        set resp.http.X-Cache = "MISS";
    }
    
    # Add cache age
    set resp.http.X-Cache-Age = resp.http.Age;
    
    # Remove backend information in production
    unset resp.http.X-Varnish;
    unset resp.http.Via;
    unset resp.http.Server;
    unset resp.http.X-Powered-By;
    
    # Add security headers
    set resp.http.X-Content-Type-Options = "nosniff";
    set resp.http.X-Frame-Options = "SAMEORIGIN";
    set resp.http.X-XSS-Protection = "1; mode=block";
    
    return (deliver);
}

sub vcl_hit {
    if (req.method == "PURGE") {
        return (synth(200, "Purged"));
    }
    return (deliver);
}

sub vcl_miss {
    if (req.method == "PURGE") {
        return (synth(404, "Not in cache"));
    }
    return (fetch);
}

sub vcl_backend_error {
    # Serve stale content if backend is down
    if (beresp.status >= 500 && beresp.status <= 599) {
        return (retry);
    }
    
    # Custom error page
    set beresp.http.Content-Type = "text/html; charset=utf-8";
    synthetic({"
        <!DOCTYPE html>
        <html>
        <head>
            <title>Service Temporarily Unavailable</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                h1 { color: #e74c3c; }
            </style>
        </head>
        <body>
            <h1>Service Temporarily Unavailable</h1>
            <p>We're experiencing technical difficulties. Please try again in a few moments.</p>
        </body>
        </html>
    "});
    
    return (deliver);
}
