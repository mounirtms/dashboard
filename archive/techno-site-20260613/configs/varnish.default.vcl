# Standard Magento 2 Varnish VCL with safe customizations
vcl 4.0;

import std;
# The minimal Varnish version is 6.0
# For SSL offloading, pass the following header in your proxy server or load balancer: 'X-Forwarded-Proto: https'

backend default {
    .host = "127.0.0.1";
    .port = "81";
    .first_byte_timeout = 300s;
    .connect_timeout = 10s;
    .between_bytes_timeout = 30s;
    .probe = {
        .request =
            "GET /health_check.php HTTP/1.1"
            "Host: technostationery.com"
            "User-Agent: Varnish Health Check"
            "Accept: */*"
            "Connection: close";
        .timeout = 3s;
        .interval = 10s;
        .window = 5;
        .threshold = 3;
    }
}

acl purge {
    "localhost";
    "127.0.0.1";
}

acl scanners {
    "96.126.117.80";   # nmap/vulnerability scanner
    "34.97.175.100";   # .git/config scanner
    "34.73.86.54";     # .git/config scanner
    "34.97.204.142";   # .git/config scanner
    "34.40.48.120";    # .git/config scanner
    "35.199.51.19";    # .git/config scanner
    "34.176.83.157";   # docker-compose/actuator scanner
    "195.178.110.132"; # POST attack source
    "71.6.237.192";    # RootEvidence scanner
}

sub vcl_recv {
    if (req.restarts > 0) {
        set req.hash_always_miss = true;
    }

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

    if (req.method != "GET" &&
        req.method != "HEAD" &&
        req.method != "PUT" &&
        req.method != "POST" &&
        req.method != "TRACE" &&
        req.method != "OPTIONS" &&
        req.method != "DELETE") {
          return (pipe);
    }

    # We only deal with GET and HEAD by default
    if (req.method != "GET" && req.method != "HEAD") {
        return (pass);
    }

    # CRITICAL: Bypass PIM domain entirely
    if (req.http.host ~ "pim\.technostationery\.com") {
        return (pass);
    }

    # CRITICAL: Bypass dashboard domain entirely
    if (req.http.host ~ "dashboard\.technostationery\.com") {
        return (pass);
    }

    # Bot protection - Block malicious login attempts with extremely long base64 (1000+ chars = abuse)
    # Allow legitimate Magento base64 redirects which are typically under 200 chars
    if (req.url ~ "/customer/account/login/referer/" && req.url ~ "aHR0c[a-zA-Z0-9+/=]{500}") {
        return (synth(403, "Blocked: Bot login spam"));
    }

    # Block search with malicious payloads
    if (req.url ~ "/search/" && req.url ~ "(checkout%20cart%20add|%E4%BF%8F%E5%B0%BF)") {
        return (synth(403, "Blocked: Malicious search query"));
    }

    # ==========================================
    # WAF RULES - Web Application Firewall
    # ==========================================

    # SQL Injection Protection (only block obvious attacks)
    # Skip WAF for static files (fonts, CSS, JS) to prevent false positives on cache-busting params
    if (req.url !~ "^/(pub/)?(static|media|assets)/") {
        if (req.url ~ "(?i)(union\s+select|select.*from.*into|insert\s+into|update.*set.*=|delete\s+from|drop\s+table|alter\s+table)" ||
            req.url ~ "(?i)(0x[0-9a-f]+|concat\(|group_concat\()") {
            return (synth(403, "Blocked: SQL Injection attempt"));
        }
    }

    # XSS (Cross-Site Scripting) Protection
    if (req.url ~ "(?i)(<script|javascript:|onerror=|onload=)" ||
        req.url ~ "(?i)(alert\(|confirm\(|prompt\(|document\.cookie)") {
        return (synth(403, "Blocked: XSS attempt"));
    }

    # Directory Traversal Protection
    if (req.url ~ "(\.\./|\.\.\\)" ||
        req.url ~ "(?i)(etc/passwd|etc/shadow|wp-config\.php)") {
        return (synth(403, "Blocked: Directory traversal attempt"));
    }

    # Block malicious security scanners
    if (req.http.User-Agent ~ "(?i)(sqlmap|nikto|nmap|masscan|dirbuster|gobuster|wpscan|joomscan|nessus|acunetix|RootEvidence)") {
        return (synth(403, "Blocked: Malicious security scanner"));
    }

    # Block access to sensitive files and directories
    if (req.url ~ "(?i)(\.env|\.git|\.svn|\.hg|\.htaccess|\.htpasswd|docker-compose|actuator|\.DS_Store|\.well-known/security\.txt$|wp-config|xmlrpc|wp-admin|wp-login|wp-content|wp-includes|cgi-bin|\.asp|\.aspx|\.jsp)") {
        return (synth(403, "Blocked: Sensitive file access attempt"));
    }

    # Block common vulnerability scanning patterns
    # EXCEPTION: Allow /static/*/vendor/ paths — Magento extensions (Amasty, etc.)
    # deploy legitimate JS/CSS vendor files under these paths
    if (req.url !~ "^/(pub/)?(static|media|assets)/") {
        if (req.url ~ "(?i)(/vendor/|/node_modules/|/configs/|/app/etc/|/var/log/|/scripts/|/backup|/phpmyadmin|/phpinfo|/info\.php|/server-status|/server-info|\.map$|\.sql$|\.bak$|\.old$|\.orig$|\.swp$)") {
            return (synth(403, "Blocked: Vulnerability scanning attempt"));
        }
    }

    # Block known scanner IPs
    if (client.ip ~ scanners) {
        return (synth(403, "Blocked: Known scanner IP"));
    }

    # Block empty user agents on sensitive pages
    if (!req.http.User-Agent && req.url ~ "/(customer|checkout|api|admin|sysadminy)") {
        return (synth(403, "Blocked: Empty user agent not allowed"));
    }

    # Bypass customer, shopping cart, checkout, and other dynamic paths
    if (req.url ~ "/customer" || req.url ~ "/checkout" || req.url ~ "/catalogsearch" || req.url ~ "/sysadminy" || req.url ~ "/login" || req.url ~ "/index\.html" || req.url ~ "/api/" || req.url ~ "/dashboard" || req.url ~ "/admin") {
        return (pass);
    }

    # Strip amnoroute parameter (Magento tracking)
    if (req.url ~ "(\?|&)amnoroute=") {
        set req.url = regsuball(req.url, "(&|\?)amnoroute=[^&]*", "");
        set req.url = regsub(req.url, "\?&", "?");
        set req.url = regsub(req.url, "\?$", "");
    }

    # Normalize search URLs - strip embedded tracking from search parameter
    if (req.url ~ "^/search/") {
        if (req.url ~ "(aHR0c|product%20[0-9]+|checkout%20cart%20add)") {
            return (synth(403, "Blocked: Malformed search query"));
        }
        set req.url = regsuball(req.url, "(&|\?)(amnoroute|cat)=[^&]*", "");
        set req.url = regsub(req.url, "\?&", "?");
        set req.url = regsub(req.url, "\?$", "");
    }

    # Bypass health check requests
    if (req.url ~ "^/(pub/)?(health_check.php)$") {
        return (pass);
    }

    # Bypass Ajax requests (Sm_ShopBy layered navigation)
    if (req.url ~ "(\?|&)ajax=1") {
        return (pass);
    }

    # Set initial grace period usage status
    set req.http.grace = "none";

    # normalize url in case of leading HTTP scheme and domain
    set req.url = regsub(req.url, "^http[s]?://", "");

    # collect all cookies
    std.collect(req.http.Cookie);

    # Strip analytics/tracking cookies
    set req.http.Cookie = regsuball(req.http.Cookie, "(^|;\s*)(_ga|_gid|_gat|__utm[a-z]|_fbp|fr|_hj|pardot|li_fat_id|__stripe_|__cfduid)=[^;]*", "");
    set req.http.Cookie = regsub(req.http.Cookie, "^;\s*", "");
    set req.http.Cookie = regsub(req.http.Cookie, ";\s*$", "");
    if (req.http.Cookie == "") {
        unset req.http.Cookie;
    }

    # Strip cookies from static files for better caching
    if (req.url ~ "^/(pub/)?(media|static|assets)/") {
        unset req.http.Cookie;
    }

    # Compression filter
    if (req.http.Accept-Encoding) {
        if (req.url ~ "\.(jpg|jpeg|png|gif|gz|tgz|bz2|tbz|mp3|ogg|swf|flv)$") {
            unset req.http.Accept-Encoding;
        } elsif (req.http.Accept-Encoding ~ "gzip") {
            set req.http.Accept-Encoding = "gzip";
        } elsif (req.http.Accept-Encoding ~ "deflate" && req.http.user-agent !~ "MSIE") {
            set req.http.Accept-Encoding = "deflate";
        } else {
            unset req.http.Accept-Encoding;
        }
    }

    # Remove all marketing get parameters to minimize the cache objects
    if (req.url ~ "(\?|&)(gclid|cx|ie|cof|siteurl|zanpid|origin|fbclid|mc_[a-z]+|utm_[a-z]+|_bta_[a-z]+)=") {
        set req.url = regsuball(req.url, "(gclid|cx|ie|cof|siteurl|zanpid|origin|fbclid|mc_[a-z]+|utm_[a-z]+|_bta_[a-z]+)=[-_A-z009+()%.]+&?", "");
        set req.url = regsub(req.url, "[?|&]+$", "");
    }

    # Static files caching - CACHE in Varnish for performance
    if (req.url ~ "^/(pub/)?(media|static|assets)/") {
        unset req.http.Cookie;
        unset req.http.Authorization;
        return (hash);
    }

    # Bypass authenticated GraphQL requests without a X-Magento-Cache-Id
    if (req.url ~ "/graphql" && !req.http.X-Magento-Cache-Id && req.http.Authorization ~ "^Bearer") {
        return (pass);
    }

    # Set forwarded headers
    if (req.restarts == 0) {
        set req.http.X-Forwarded-Proto = "https";
    }

    return (hash);
}

sub vcl_hash {
    if ((req.url !~ "/graphql" || !req.http.X-Magento-Cache-Id) && req.http.cookie ~ "X-Magento-Vary=") {
        hash_data(regsub(req.http.cookie, "^.*?X-Magento-Vary=([^;]+);*.*$", "\1"));
    }

    # To make sure http users don't see ssl warning
    if (req.http.X-Forwarded-Proto) {
        hash_data(req.http.X-Forwarded-Proto);
    }

    # Device type detection for cache separation
    if (req.http.user-agent ~ "(?i)(iPhone|iPod|Android.*Mobile|Windows Phone|BlackBerry|Opera Mini|IEMobile)") {
        hash_data("mobile");
    } elsif (req.http.user-agent ~ "(?i)(iPad|Android|Silk)") {
        hash_data("tablet");
    } else {
        hash_data("desktop");
    }

    if (req.url ~ "/graphql") {
        call process_graphql_headers;
    }
}

sub process_graphql_headers {
    if (req.http.X-Magento-Cache-Id) {
        hash_data(req.http.X-Magento-Cache-Id);
        if (req.http.Authorization ~ "^Bearer") {
            hash_data("Authorized");
        }
    }

    if (req.http.Store) {
        hash_data(req.http.Store);
    }

    if (req.http.Content-Currency) {
        hash_data(req.http.Content-Currency);
    }
}

sub vcl_backend_fetch {
    # Set proper Host header for Apache VirtualHost routing
    if (bereq.http.host ~ "^(www\.)?technostationery\.com") {
        set bereq.http.host = "technostationery.com";
    }
}

sub vcl_backend_response {

    set beresp.grace = 3d;

    # Never cache server errors (5xx) — serve fresh errors, not cached ones
    if (beresp.status >= 500) {
        set beresp.uncacheable = true;
        set beresp.ttl = 0s;
        return (deliver);
    }

    if (beresp.http.content-type ~ "text") {
        set beresp.do_esi = true;
    }

    if (bereq.url ~ "\.js$" || beresp.http.content-type ~ "text") {
        set beresp.do_gzip = true;
    }

    if (beresp.http.X-Magento-Debug) {
        set beresp.http.X-Magento-Cache-Control = beresp.http.Cache-Control;
    }

    # cache only successfully responses and 404s that are not marked as private
    if (beresp.status != 200 &&
            beresp.status != 404 &&
            beresp.http.Cache-Control ~ "private") {
        set beresp.uncacheable = true;
        set beresp.ttl = 86400s;
        return (deliver);
    }

    # validate if we need to cache it and prevent from setting cookie
    if (beresp.http.Set-Cookie) {
        # Strip Set-Cookie for static files only
        if (bereq.url ~ "^/(pub/)?(media|static|assets)/") {
            unset beresp.http.Set-Cookie;
        }
    }

   # If page is not cacheable then bypass varnish for 2 minutes as Hit-For-Pass
   # EXCEPTION: For HTML pages, override no-store if it's a normal page request
   if (beresp.ttl <= 0s ||
       beresp.http.Surrogate-control ~ "no-store" ||
       (!beresp.http.Surrogate-Control &&
       beresp.http.Cache-Control ~ "no-cache|no-store") ||
       beresp.http.Vary == "*") {
        
        # For HTML pages, force caching for 1 hour even if no-store
        if (beresp.http.content-type ~ "text/html") {
            set beresp.ttl = 3600s;
            set beresp.grace = 3600s;
            unset beresp.http.Cache-Control;
            unset beresp.http.Surrogate-Control;
            unset beresp.http.Pragma;
        } else {
            # For non-HTML, keep as uncacheable for 2 minutes
            set beresp.ttl = 120s;
            set beresp.uncacheable = true;
        }
   }

   # Static files - very long TTL (versioned URLs)
   if (bereq.url ~ "^/(pub/)?static/") {
       set beresp.ttl = 8w;
       set beresp.grace = 16w;
       unset beresp.http.set-cookie;
   }

   # Media files - long TTL
   if (bereq.url ~ "^/(pub/)?media/") {
       set beresp.ttl = 7d;
       set beresp.grace = 14d;
       unset beresp.http.set-cookie;
   }

   # If the cache key in the Magento response doesn't match the one that was sent in the request
   if (bereq.url ~ "/graphql" && bereq.http.X-Magento-Cache-Id && bereq.http.X-Magento-Cache-Id != beresp.http.X-Magento-Cache-Id) {
      set beresp.ttl = 0s;
      set beresp.uncacheable = true;
   }

    return (deliver);
}

sub vcl_deliver {
    if (resp.http.x-varnish ~ " ") {
        set resp.http.X-Magento-Cache-Debug = "HIT";
        set resp.http.Grace = req.http.grace;
    } else {
        set resp.http.X-Magento-Cache-Debug = "MISS";
    }

    # Not letting browser to cache non-static files.
    if (resp.http.Cache-Control !~ "private" && req.url !~ "^/(pub/)?(media|static|assets)/") {
        set resp.http.Pragma = "no-cache";
        set resp.http.Expires = "-1";
        set resp.http.Cache-Control = "no-store, no-cache, must-revalidate, max-age=0";
    }

    # Set proper cache headers for static files
    if (req.url ~ "^/(pub/)?(media|static|assets)/") {
        set resp.http.Cache-Control = "public, max-age=604800";
        set resp.http.Expires = resp.http.date + 7d;
    }

    if (!resp.http.X-Magento-Debug) {
        unset resp.http.Age;
    }
    unset resp.http.X-Magento-Debug;
    unset resp.http.X-Magento-Tags;
    unset resp.http.X-Powered-By;
    unset resp.http.Server;
    unset resp.http.X-Varnish;
    unset resp.http.Via;
    unset resp.http.Link;
}

sub vcl_hit {
    if (obj.ttl >= 0s) {
        return (deliver);
    }
    if (std.healthy(req.backend_hint)) {
        if (obj.ttl + 300s > 0s) {
            set req.http.grace = "normal (healthy server)";
            return (deliver);
        } else {
            return (restart);
        }
    } else {
        set req.http.grace = "unlimited (unhealthy server)";
        return (deliver);
    }
}
