# Standard Magento 2 Varnish VCL
vcl 4.0;

import std;

# Backend: Apache on port 8080 (Varnish will handle port 80)
backend default {
    .host = "205.134.249.177";
    .port = "8080";
    .first_byte_timeout = 600s;
    .connect_timeout = 600s;
    .between_bytes_timeout = 60s;
    .probe = {
        .request =
            "GET /pub/health_check.php HTTP/1.1"
            "Host: technostationery.com"
            "X-Forwarded-Proto: https"
            "Connection: close"
            "Accept-Encoding: gzip";
        .timeout = 2s;
        .interval = 5s;
        .window = 10;
        .threshold = 5;
    }
}

acl purge {
    "localhost";
    "127.0.0.1";
}

sub vcl_recv {
    if (req.restarts > 0) {
        set req.hash_always_miss = true;
    }

    # CRITICAL: Bypass PIM domain entirely - MUST be first check
    # Akeneo PIM has its own session management and should never be cached
    if (req.http.host ~ "pim\.technostationery\.com") {
        return (pass);
    }

    # Handle PURGE requests
    if (req.method == "PURGE") {
        if (client.ip !~ purge) {
            return (synth(405, "Method not allowed"));
        }
        if (!req.http.X-Magento-Tags-Pattern && !req.http.X-Pool) {
            return (synth(400, "X-Magento-Tags-Pattern or X-Pool header required"));
        }
        if (req.http.X-Magento-Tags-Pattern) {
          ban("obj.http.X-Magento-Tags ~ " + req.http.X-Magento-Tags-Pattern);
        }
        if (req.http.X-Pool) {
          ban("obj.http.X-Pool ~ " + req.http.X-Pool);
        }
        return (synth(200, "Purged"));
    }

    # Only cache GET and HEAD
    if (req.method != "GET" && req.method != "HEAD") {
        return (pass);
    }

    # Bypass customer, shopping cart, checkout, login, search, admin, API, dashboard, PIM, and index.html
    if (req.url ~ "/customer" || req.url ~ "/checkout" || req.url ~ "/catalogsearch" || req.url ~ "/sysadminy" || req.url ~ "/login" || req.url ~ "/index\.html" || req.url ~ "/api/" || req.url ~ "/dashboard" || req.url ~ "/admin") {
        return (pass);
    }
    
    # AGGRESSIVE BOT PROTECTION - Block malicious login attempts
    # Block login pages with massive base64 redirect tracking (bot abuse)
    if (req.url ~ "/customer/account/login/referer/" && req.url ~ "(aHR0c|~$|%2C)") {
        return (synth(403, "Blocked: Malformed login redirect"));
    }
    
    # Block checkout/cart/add requests (should not be cached anyway)
    if (req.url ~ "/checkout/cart/add/") {
        return (synth(403, "Blocked: Direct cart access"));
    }
    
    # Block search with malicious payloads
    if (req.url ~ "/search/" && req.url ~ "(checkout%20cart%20add|%E4%BF%8F%E5%B0%BF)") {
        return (synth(403, "Blocked: Malicious search query"));
    }
    
    # Strip amnoroute parameter (Magento tracking)
    if (req.url ~ "(\?|&)amnoroute=") {
        set req.url = regsuball(req.url, "(&|\?)amnoroute=[^&]*", "");
        set req.url = regsub(req.url, "\?&", "?");
        set req.url = regsub(req.url, "\?$", "");
    }

    # Normalize search URLs - strip embedded tracking from search parameter
    if (req.url ~ "^/search/") {
        # Block search URLs with massive base64-encoded tracking (bot abuse)
        # These contain "product XXXX" and "aHR0c" (base64 http) in the search term
        if (req.url ~ "(aHR0c|product%20[0-9]+|checkout%20cart%20add)") {
            # Return 403 to prevent overloading PHP-FPM
            return (synth(403, "Blocked: Malformed search query"));
        }
        
        # Keep search term but strip additional tracking params
        set req.url = regsuball(req.url, "(&|\?)(p|amnoroute|cat)=[^&]*", "");
        set req.url = regsub(req.url, "\?&", "?");
        set req.url = regsub(req.url, "\?$", "");
    }

    # Bypass health check
    if (req.url ~ "^/pub/health_check\.php$") {
        return (pass);
    }

    # Normalize URL
    set req.url = regsub(req.url, "^http[s]?://", "");

    # Collect cookies
    std.collect(req.http.Cookie);

    # Remove marketing parameters
    if (req.url ~ "(\?|&)(gclid|cx|ie|coq|siteurl|zanpid|origin|fbclid|mc_[a-z]+|utm_[a-z]+|_bta_[a-z]+)=") {
        set req.url = regsuball(req.url, "(gclid|cx|ie|coq|siteurl|zanpid|origin|fbclid|mc_[a-z]+|utm_[a-z]+|_bta_[a-z]+)=[-_A-z0-9+()%.]+&?", "");
        set req.url = regsub(req.url, "[?|&]+$", "");
    }

    # Static files - cache in Varnish
    if (req.url ~ "^/(pub/)?(media|static)/") {
        unset req.http.Cookie;
        return (hash);
    }

    # Strip all cookies for anonymous users
    # Varnish will cache pages that don't have Set-Cookie in response
    if (req.http.Cookie) {
        # Remove all cookies - this allows Varnish to cache pages
        # Magento's FPC will still work for personalized content via ESI
        unset req.http.Cookie;
    }

    # Device type tracking - log for analytics
    if (req.http.user-agent ~ "(?i)(iPhone|iPod|Android.*Mobile|Windows Phone|BlackBerry|Opera Mini|IEMobile)") {
        std.log("device:mobile");
    } elsif (req.http.user-agent ~ "(?i)(iPad|Android|Silk)") {
        std.log("device:tablet");
    } else {
        std.log("device:desktop");
    }

    # Set forwarded headers
    if (req.restarts == 0) {
        set req.http.X-Forwarded-Proto = "https";
    }

    return (hash);
}

sub vcl_hash {
    # Normalize URL for better cache hit rate
    if (req.url ~ "\?") {
        # Strip ALL filter/sort params that don't change base page for anonymous users
        set req.url = regsuball(req.url, "(&|\?)(color|mgs_brand|price|product_list_order|product_list_dir|a_la_une|cat)=[^&]*", "");
        
        # Strip session IDs (?SID=xxx, ?sid=xxx)
        set req.url = regsuball(req.url, "(&|\?)(SID|sid|sessionid)=[^&]*", "");
        
        # Strip ALL tracking/timestamp params
        set req.url = regsuball(req.url, "(&|\?)(utm_[a-z]+|gclid|fbclid|mc_[a-z]+|_bta_[a-z]+|amnoroute|_=[0-9]+)=[^&]*", "");
        
        # Strip AJAX params
        set req.url = regsuball(req.url, "(&|\?)(isAjax|ajax|format)=[^&]*", "");
        
        # Normalize pagination - keep page number but normalize format
        # ?p=1 should be same as no page parameter
        set req.url = regsuball(req.url, "(&|\?)p=1(&|$)", "");
        
        set req.url = regsub(req.url, "\?&", "?");
        set req.url = regsub(req.url, "\?$", "");
    }
    
    hash_data(req.url);
    
    if (req.http.X-Forwarded-Proto) {
        hash_data(req.http.X-Forwarded-Proto);
    }

    # Add device type to cache hash so mobile/desktop get different cached pages
    if (req.http.user-agent ~ "(?i)(iPhone|iPod|Android.*Mobile|Windows Phone|BlackBerry|Opera Mini|IEMobile)") {
        hash_data("mobile");
    }
}

sub vcl_backend_fetch {
    # Set proper Host header for Apache VirtualHost routing
    if (bereq.http.host ~ "^(www\.)?technostationery\.com") {
        set bereq.http.host = "technostationery.com";
    }
}

sub vcl_backend_response {
    set beresp.grace = 7d;  # Increased from 3d for better hit rate

    # Static files - very long TTL (versioned URLs)
    if (bereq.url ~ "^/(pub/)?static/") {
        set beresp.ttl = 8w;  # Increased from 4w
        set beresp.grace = 16w;  # Increased from 8w
        unset beresp.http.set-cookie;
    }

    # Media files - very long TTL
    if (bereq.url ~ "^/(pub/)?media/") {
        set beresp.ttl = 30d;  # Increased from 7d
        set beresp.grace = 60d;  # Increased from 14d
        unset beresp.http.set-cookie;
    }

    # Cache HTML pages - ignore no-cache headers for anonymous users
    # BUT bypass login, admin, API, dashboard, PIM, and index.html pages
    if (bereq.url !~ "/customer" && bereq.url !~ "/checkout" && bereq.url !~ "/admin" && bereq.url !~ "/sysadminy" && bereq.url !~ "/wishlist" && bereq.url !~ "/login" && bereq.url !~ "/index\.html" && bereq.url !~ "/api/" && bereq.url !~ "/dashboard" && bereq.http.host !~ "pim\.technostationery\.com") {
        # Force caching of HTML pages with longer TTL
        if (beresp.http.content-type ~ "text/html") {
            # Product and category pages - 12 hours (increased from 6)
            if (bereq.url ~ "\.html$" && bereq.url !~ "/index\.html") {
                set beresp.ttl = 43200s;  # 12 hours
                set beresp.grace = 14d;   # 14 days grace
            } elsif (bereq.url ~ "^/(catalog|search)/") {
                # Category and search pages - 6 hours
                set beresp.ttl = 21600s;  # 6 hours
                set beresp.grace = 7d;
            } else {
                # Other HTML pages - 4 hours (increased from 2)
                set beresp.ttl = 14400s;  # 4 hours
                set beresp.grace = 7d;    # 7 days grace
            }
            unset beresp.http.set-cookie;
            unset beresp.http.pragma;
            unset beresp.http.cache-control;
        }
    }

    # Don't cache private responses
    if (beresp.http.Cache-Control ~ "private") {
        set beresp.uncacheable = true;
        set beresp.ttl = 86400s;
        return (deliver);
    }

    return (deliver);
}

sub vcl_deliver {
    # Add device type header for debugging
    if (req.http.user-agent ~ "(?i)(iPhone|iPod|Android.*Mobile|Windows Phone|BlackBerry|Opera Mini|IEMobile)") {
        set resp.http.X-Device-Type = "mobile";
    } elsif (req.http.user-agent ~ "(?i)(iPad|Android|Silk)") {
        set resp.http.X-Device-Type = "tablet";
    } else {
        set resp.http.X-Device-Type = "desktop";
    }

    if (obj.hits > 0) {
        set resp.http.X-Magento-Cache-Debug = "HIT";
    } else {
        set resp.http.X-Magento-Cache-Debug = "MISS";
    }

    # Don't let browser cache non-static files
    if (resp.http.Cache-Control !~ "private" && req.url !~ "^/(pub/)?(media|static)/") {
        set resp.http.Pragma = "no-cache";
        set resp.http.Expires = "-1";
        set resp.http.Cache-Control = "no-store, no-cache, must-revalidate, max-age=0";
    }

    unset resp.http.X-Magento-Debug;
    unset resp.http.X-Magento-Tags;
    unset resp.http.X-Powered-By;
    unset resp.http.Server;
    unset resp.http.X-Varnish;
    unset resp.http.Via;
    unset resp.http.Link;
}
