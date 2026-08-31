vcl 4.1;

import std;

# ============================================================================
# BACKEND CONFIGURATION - Multi-Website Setup
# Using public IP 205.134.249.177 for Apache vhosts
# ============================================================================

backend prod {
    .host = "205.134.249.177";
    .port = "80";
    .connect_timeout = 5s;
    .first_byte_timeout = 120s;
    .between_bytes_timeout = 60s;
    .max_connections = 300;
    .probe = {
        .request =
            "GET /.well-known/acme-challenge/health HTTP/1.1"
            "Host: technostationery.com"
            "Connection: close";
        .timeout = 3s;
        .interval = 10s;
        .window = 5;
        .threshold = 3;
    }
}

backend beta {
    .host = "205.134.249.177";
    .port = "80";
    .connect_timeout = 5s;
    .first_byte_timeout = 180s;
    .between_bytes_timeout = 90s;
    .max_connections = 150;
    .probe = {
        .request =
            "GET /.well-known/acme-challenge/health HTTP/1.1"
            "Host: beta.technostationery.com"
            "Connection: close";
        .timeout = 3s;
        .interval = 10s;
        .window = 5;
        .threshold = 3;
    }
}

backend dashboard {
    .host = "127.0.0.1";
    .port = "80";
    .connect_timeout = 3s;
    .first_byte_timeout = 60s;
    .between_bytes_timeout = 30s;
    .max_connections = 50;
}

backend lms {
    .host = "205.134.249.177";
    .port = "80";
    .connect_timeout = 5s;
    .first_byte_timeout = 120s;
    .between_bytes_timeout = 60s;
    .max_connections = 100;
}

backend pim {
    .host = "127.0.0.1";
    .port = "80";
    .connect_timeout = 10s;
    .first_byte_timeout = 300s;
    .between_bytes_timeout = 120s;
    .max_connections = 50;
}

# ============================================================================
# ACL - ACCESS CONTROL
# ============================================================================

acl purge {
    "localhost";
    "127.0.0.1";
    "::1";
    "205.134.249.177";
}

acl cloudflare {
    "173.245.48.0"/20;
    "103.21.244.0"/22;
    "103.22.200.0"/22;
    "103.31.4.0"/22;
    "141.101.64.0"/18;
    "108.162.192.0"/18;
    "190.93.240.0"/20;
    "188.114.96.0"/20;
    "197.234.240.0"/22;
    "198.41.128.0"/17;
    "162.158.0.0"/15;
    "104.16.0.0"/13;
    "104.24.0.0"/14;
    "172.64.0.0"/13;
    "131.0.72.0"/22;
    "2400:cb00::"/32;
    "2606:4700::"/32;
    "2803:f800::"/32;
    "2405:b500::"/32;
    "2405:8100::"/32;
    "2a06:98c0::"/29;
    "2c0f:f248::"/32;
}

# ============================================================================
# VCL_RECV - REQUEST PROCESSING
# ============================================================================

sub vcl_recv {
    # Trust Cloudflare real IP
    if (client.ip ~ cloudflare) {
        if (req.http.CF-Connecting-IP) {
            set req.http.X-Forwarded-For = req.http.CF-Connecting-IP;
        }
    }

    # Always assume HTTPS from Cloudflare or if X-Forwarded-Proto is set
    if (req.http.X-Forwarded-Proto !~ "(?i)https" && (client.ip ~ cloudflare || req.http.CF-Visitor ~ "https")) {
        set req.http.X-Forwarded-Proto = "https";
    }

    # Route to correct backend
    if (req.http.host ~ "^(www\.)?technostationery\.com(\.dz)?$") {
        set req.backend_hint = prod;
    } elsif (req.http.host ~ "^beta\.technostationery\.com$") {
        set req.backend_hint = beta;
    } elsif (req.http.host ~ "^dashboard\.technostationery\.com$") {
        set req.backend_hint = dashboard;
        return (pass); # Dashboard always pass
    } elsif (req.http.host ~ "^lms\.technostationery\.com$") {
        set req.backend_hint = lms;
    } elsif (req.http.host ~ "^pim\.technostationery\.com$") {
        set req.backend_hint = pim;
        return (pass);
    } else {
        set req.backend_hint = prod;
    }

    # Improved Device Detection (Tablet first to avoid mobile mis-detection)
    if (req.http.User-Agent ~ "(?i)(ipad|android 3|sch-i800|playbook|tablet|kindle|silk)") {
        set req.http.X-Device = "tablet";
    } elsif (req.http.User-Agent ~ "(?i)(mobile|android|iphone|ipod|blackberry|webos|opera mini|windows phone)") {
        set req.http.X-Device = "mobile";
    } else {
        set req.http.X-Device = "desktop";
    }

    # PURGE Support
    if (req.method == "PURGE") {
        if (!client.ip ~ purge && !client.ip ~ cloudflare) {
            return(synth(405, "Not allowed"));
        }
        return(purge);
    }

    # BAN Support
    if (req.method == "BAN") {
        if (!client.ip ~ purge) {
            return(synth(405, "Not allowed"));
        }
        ban("obj.http.X-Varnish-Host == " + req.http.host + " && obj.http.x-url ~ " + req.url);
        return(synth(200, "Banned"));
    }

    # Only GET/HEAD are cacheable
    if (req.method != "GET" && req.method != "HEAD") {
        return (pass);
    }

    # Never cache dynamic routes
    if (req.url ~ "^/(pub/)?(admin|customer|checkout|sales|rest|graphql|api|contact|login|logout|cart)") {
        return (pass);
    }

    # Static assets - aggressive cache
    if (req.url ~ "\.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot|otf|webp|bmp|tiff|mp4|webm|mp3|pdf|zip|tar|gz)(\?.*)?$") {
        set req.url = regsub(req.url, "\?.*$", "");
        unset req.http.Cookie;
        return (hash);
    }

    # Tracking parameters removal
    if (req.url ~ "(\?|&)(utm_source|utm_medium|utm_campaign|utm_content|utm_term|gclid|fbclid|_ga|_gid)=") {
        set req.url = regsuball(req.url, "&(utm_source|utm_medium|utm_campaign|utm_content|utm_term|gclid|fbclid|_ga|_gid)=([A-z0-9_\-\.%25]+)", "");
        set req.url = regsuball(req.url, "\?(utm_source|utm_medium|utm_campaign|utm_content|utm_term|gclid|fbclid|_ga|_gid)=([A-z0-9_\-\.%25]+)", "?");
        set req.url = regsub(req.url, "\?&", "?");
        set req.url = regsub(req.url, "\?$", "");
    }

    # Cookie cleaning
    if (req.http.Cookie) {
        set req.http.Cookie = regsuball(req.http.Cookie, "(^|;\s*)(_ga|_gid|_gat|__utm[a-z]|__gads|__qc|__atuv|_fbp|fr)=[^;]*", "");
        set req.http.Cookie = regsuball(req.http.Cookie, "^;\s*", "");
        if (req.http.Cookie == "") {
            unset req.http.Cookie;
        }
    }

    # Pass Magento session cookies
    if (req.http.Cookie ~ "frontend=|adminhtml=|form_key|private_content_version|X-Magento-Vary") {
        return (pass);
    }

    return (hash);
}

sub vcl_hash {
    hash_data(req.url);
    if (req.http.host) {
        hash_data(req.http.host);
    } else {
        hash_data(server.ip);
    }

    # Device-specific variance
    if (req.http.X-Device) {
        hash_data(req.http.X-Device);
    }

    # Proto variance
    if (req.http.X-Forwarded-Proto) {
        hash_data(req.http.X-Forwarded-Proto);
    }

    return (lookup);
}

sub vcl_backend_response {
    set beresp.http.x-url = bereq.url;
    set beresp.http.X-Varnish-Host = bereq.http.host;

    # Static files - 30 day TTL (was 365d — caused Storage Full + 7521 LRU evictions)
    if (bereq.url ~ "\.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot|otf|webp|bmp)$") {
        set beresp.ttl = 30d;
        set beresp.http.Cache-Control = "public, max-age=2592000";
        unset beresp.http.Set-Cookie;
        return (deliver);
    }

    # HTML pages
    if (beresp.http.content-type ~ "text/html") {
        # Never cache if Magento session cookie is set in response
        if (beresp.http.Set-Cookie ~ "frontend=|adminhtml=|form_key") {
            set beresp.ttl = 0s;
            set beresp.uncacheable = true;
            return (deliver);
        }

        # Force cache if not specified otherwise (Magento 2 Varnish mode fallback)
        if (beresp.ttl <= 0s && beresp.http.Cache-Control !~ "private|no-cache|no-store") {
             set beresp.ttl = 2h;
        }

        # Set Vary for device
        if (beresp.http.Vary) {
            set beresp.http.Vary = beresp.http.Vary + ", X-Device";
        } else {
            set beresp.http.Vary = "X-Device";
        }
    }

    # Grace and keep — reduced to free storage (was grace=24h/keep=8h → caused LRU saturation)
    set beresp.grace = 6h;
    set beresp.keep = 2h;

    return (deliver);
}

sub vcl_deliver {
    if (obj.hits > 0) {
        set resp.http.X-Cache = "HIT";
        set resp.http.X-Cache-Hits = obj.hits;
    } else {
        set resp.http.X-Cache = "MISS";
    }

    if (req.http.X-Device) {
        set resp.http.X-Device = req.http.X-Device;
    }

    # Security & Cleanup
    unset resp.http.x-url;
    unset resp.http.X-Varnish-Host;
    unset resp.http.Via;
    unset resp.http.X-Varnish;
    unset resp.http.X-Powered-By;
    
    set resp.http.X-Cache-Age = resp.http.Age;
    
    return (deliver);
}
