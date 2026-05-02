vcl 4.1;

import std;

# ============================================================================
# BACKEND CONFIGURATION
# ============================================================================
backend default {
    .host = "127.0.0.1";
    .port = "80";
    .connect_timeout = 5s;
    .first_byte_timeout = 60s;
    .between_bytes_timeout = 10s;
    .max_connections = 300;
    .probe = {
        .url = "/health_check.php";
        .timeout = 2s;
        .interval = 10s;
        .window = 5;
        .threshold = 3;
    }
}

# ============================================================================
# ACL - ALLOWED PURGE IPS
# ============================================================================
acl purge {
    "localhost";
    "127.0.0.1";
    "::1";
}

# ============================================================================
# VCL_RECV - REQUEST PROCESSING
# ============================================================================
sub vcl_recv {
    # Allow purging from trusted IPs
    if (req.method == "PURGE") {
        if (!client.ip ~ purge) {
            return(synth(405, "Not allowed"));
        }
        return(purge);
    }
    
    # Allow BAN from trusted IPs
    if (req.method == "BAN") {
        if (!client.ip ~ purge) {
            return(synth(405, "Not allowed"));
        }
        ban("obj.http.x-url ~ " + req.url);
        return(synth(200, "Banned"));
    }
    
    # Only handle GET, HEAD, PUT, POST, TRACE, OPTIONS, DELETE
    if (req.method != "GET" &&
        req.method != "HEAD" &&
        req.method != "PUT" &&
        req.method != "POST" &&
        req.method != "TRACE" &&
        req.method != "OPTIONS" &&
        req.method != "DELETE") {
        return (pipe);
    }
    
    # Only cache GET and HEAD
    if (req.method != "GET" && req.method != "HEAD") {
        return (pass);
    }
    
    # ========================================================================
    # MAGENTO SPECIFIC RULES
    # ========================================================================
    
    # Never cache admin, customer, checkout
    if (req.url ~ "^/(admin|customer|checkout|sales|rest|graphql)") {
        return (pass);
    }
    
    # Never cache API endpoints
    if (req.url ~ "^/api/") {
        return (pass);
    }
    
    # Never cache Magento cron
    if (req.url ~ "^/cron\.php") {
        return (pass);
    }
    
    # ========================================================================
    # STATIC FILES - AGGRESSIVE CACHING
    # ========================================================================
    
    # Remove query strings from static files
    if (req.url ~ "\.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot|otf|webp|bmp|tiff|mp4|webm|mp3|pdf|doc|docx|xls|xlsx|zip|tar|gz)(\\?.*)?$") {
        # Remove version parameters
        set req.url = regsub(req.url, "\\?.*$", "");
        # Remove all cookies for static files
        unset req.http.Cookie;
        return (hash);
    }
    
    # ========================================================================
    # COOKIE HANDLING
    # ========================================================================
    
    # Remove Google Analytics cookies
    set req.http.Cookie = regsuball(req.http.Cookie, "(^|;\\s*)(_ga|_gid|_gat|__utm[a-z])=[^;]*", "");
    
    # Remove Facebook, Twitter tracking cookies
    set req.http.Cookie = regsuball(req.http.Cookie, "(^|;\\s*)(_fb[a-z]*|fr)=[^;]*", "");
    
    # Remove empty cookies
    set req.http.Cookie = regsuball(req.http.Cookie, "^;\\s*", "");
    
    if (req.http.Cookie == "") {
        unset req.http.Cookie;
    }
    
    # ========================================================================
    # MAGENTO SESSION HANDLING
    # ========================================================================
    
    # Pass if user has Magento session
    if (req.http.Cookie ~ "frontend=|adminhtml=") {
        return (pass);
    }
    
    # Pass if user recently added to cart (form_key indicates session)
    if (req.http.Cookie ~ "form_key") {
        return (pass);
    }
    
    # ========================================================================
    # NORMALIZE HEADERS
    # ========================================================================
    
    # Normalize Accept-Encoding
    if (req.http.Accept-Encoding) {
        if (req.url ~ "\.(jpg|jpeg|png|gif|gz|tgz|bz2|tbz|mp3|ogg|swf|woff|woff2|ttf|eot)$") {
            # Don't compress already compressed formats
            unset req.http.Accept-Encoding;
        } elsif (req.http.Accept-Encoding ~ "gzip") {
            set req.http.Accept-Encoding = "gzip";
        } elsif (req.http.Accept-Encoding ~ "deflate") {
            set req.http.Accept-Encoding = "deflate";
        } else {
            unset req.http.Accept-Encoding;
        }
    }
    
    # Remove unnecessary headers
    unset req.http.X-Forwarded-For;
    
    return (hash);
}

# ============================================================================
# VCL_HASH - CACHE KEY GENERATION
# ============================================================================
sub vcl_hash {
    hash_data(req.url);
    
    if (req.http.host) {
        hash_data(req.http.host);
    } else {
        hash_data(server.ip);
    }
    
    # Hash on currency if present (Magento multi-currency)
    if (req.http.Cookie ~ "currency=") {
        hash_data(req.http.Cookie);
    }
    
    # Hash on store code if present (Magento multi-store)
    if (req.http.Cookie ~ "store=") {
        hash_data(req.http.Cookie);
    }
    
    return (lookup);
}

# ============================================================================
# VCL_BACKEND_RESPONSE - RESPONSE FROM BACKEND
# ============================================================================
sub vcl_backend_response {
    # Store URL in object for ban support
    set beresp.http.x-url = bereq.url;
    
    # ========================================================================
    # STATIC FILES - LONG TTL
    # ========================================================================
    if (bereq.url ~ "\.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot|otf|webp|bmp)$") {
        set beresp.ttl = 30d;
        set beresp.http.Cache-Control = "public, max-age=2592000";
        unset beresp.http.Set-Cookie;
        unset beresp.http.Vary;
        return (deliver);
    }
    
    # ========================================================================
    # HTML PAGES - MEDIUM TTL WITH GRACE
    # ========================================================================
    if (beresp.http.content-type ~ "text/html") {
        # Don't cache if user-specific
        if (beresp.http.Set-Cookie ~ "frontend=|adminhtml=") {
            set beresp.ttl = 0s;
            set beresp.uncacheable = true;
            return (deliver);
        }
        
        # Cache homepage and category pages longer
        if (bereq.url ~ "^/$|^/[a-z0-9-]+\.html$") {
            set beresp.ttl = 1h;
            set beresp.http.Cache-Control = "public, max-age=3600";
            set beresp.grace = 6h;
        } else {
            set beresp.ttl = 15m;
            set beresp.http.Cache-Control = "public, max-age=900";
            set beresp.grace = 2h;
        }
        
        # Enable ESI
        set beresp.do_esi = true;
    }
    
    # ========================================================================
    # ERROR HANDLING
    # ========================================================================
    
    # Don't cache errors
    if (beresp.status >= 500) {
        set beresp.ttl = 0s;
        set beresp.grace = 30s;
        return (deliver);
    }
    
    # Short cache for 404
    if (beresp.status == 404) {
        set beresp.ttl = 60s;
        return (deliver);
    }
    
    # ========================================================================
    # GRACE MODE - SERVE STALE CONTENT ON BACKEND FAILURE
    # ========================================================================
    
    # Default grace period
    set beresp.grace = 2h;
    
    # Keep objects in cache even after TTL expires (for grace)
    set beresp.keep = 8h;
    
    return (deliver);
}

# ============================================================================
# VCL_DELIVER - FINAL DELIVERY TO CLIENT
# ============================================================================
sub vcl_deliver {
    # Add cache hit/miss header for debugging
    if (obj.hits > 0) {
        set resp.http.X-Cache = "HIT";
        set resp.http.X-Cache-Hits = obj.hits;
    } else {
        set resp.http.X-Cache = "MISS";
    }
    
    # Add age header
    set resp.http.X-Cache-Age = resp.http.Age;
    
    # Remove internal headers (production only, comment for debugging)
    unset resp.http.x-url;
    unset resp.http.X-Varnish;
    unset resp.http.Via;
    unset resp.http.X-Generator;
    unset resp.http.X-Powered-By;
    
    # Add server timing header for performance monitoring
    if (obj.hits > 0) {
        set resp.http.Server-Timing = "cache;desc=hit";
    } else {
        set resp.http.Server-Timing = "cache;desc=miss";
    }
    
    return (deliver);
}

# ============================================================================
# VCL_HIT - OBJECT FOUND IN CACHE
# ============================================================================
sub vcl_hit {
    # Serve stale content if backend is down
    if (obj.ttl >= 0s) {
        return (deliver);
    }
    
    # Serve stale content during grace period
    if (obj.ttl + obj.grace > 0s) {
        return (deliver);
    }
    
    # Fetch fresh content
    return (restart);
}

# ============================================================================
# VCL_BACKEND_ERROR - BACKEND IS DOWN
# ============================================================================
sub vcl_backend_error {
    # Serve stale content if available (grace mode)
    if (bereq.retries == 0) {
        # Try again once
        return (retry);
    }
    
    # Return error page
    set beresp.http.Content-Type = "text/html; charset=utf-8";
    synthetic({"
        <!DOCTYPE html>
        <html>
        <head>
            <title>Service Temporarily Unavailable</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                h1 { color: #d9534f; }
            </style>
        </head>
        <body>
            <h1>Service Temporarily Unavailable</h1>
            <p>We're working to restore service. Please try again in a moment.</p>
            <p><small>Error: "} + beresp.status + " " + beresp.reason + {"</small></p>
        </body>
        </html>
    "});
    
    return (deliver);
}
