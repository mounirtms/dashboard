# Varnish Configuration for Magento 2.4.6 Production
# File: /etc/varnish/default.vcl

vcl 4.1;

import std;
import directors;

# Backend configuration
backend default {
    .host = "localhost";
    .port = "8080";
    .first_byte_timeout = 600s;
    .between_bytes_timeout = 60s;
    .probe = {
        .url = "/pub/health_check.php";
        .timeout = 2s;
        .interval = 5s;
        .window = 10;
        .threshold = 5;
    }
}

# ACL for purging
acl purge {
    "localhost";
    "127.0.0.1";
}

sub vcl_recv {
    # Remove port from host header
    set req.http.Host = regsub(req.http.Host, ":[0-9]+", "");

    # Remove tracking parameters
    if (req.url ~ "(\?|&)(gclid|cx|ie|cof|siteurl|zanpid|origin|utm_[a-z]+|mr:[A-z]+)=") {
        set req.url = regsuball(req.url, "&(gclid|cx|ie|cof|siteurl|zanpid|origin|utm_[a-z]+|mr:[A-z]+)=[A-z0-9_\-\.%3A]+", "");
        set req.url = regsuball(req.url, "\?(gclid|cx|ie|cof|siteurl|zanpid|origin|utm_[a-z]+|mr:[A-z]+)=[A-z0-9_\-\.%3A]+&?", "?");
        set req.url = regsub(req.url, "\?$", "");
    }

    # Static files caching
    if (req.url ~ "^/(pub/)?(media|static)/") {
        # Static files should not be cached by browser beyond 1 year
        unset req.http.Https;
        unset req.http.X-Forwarded-Proto;
        return (hash);
    }

    # Health check
    if (req.url ~ "^/(pub/)?health_check.php$") {
        return (pipe);
    }

    # Admin panel
    if (req.url ~ "/admin") {
        return (pass);
    }

    # Purge request
    if (req.method == "PURGE") {
        if (client.ip !~ purge) {
            return(synth(405,"Method not allowed"));
        }
        # To use the X-Pool header for purging varnish during automated deployments
        if (req.http.X-Pool) {
            ban("obj.http.X-Pool ~ " + req.http.X-Pool);
        } else {
            ban("req.url ~ " + req.url + " && obj.http.host == " + req.http.host);
        }
        return(synth(200, "Purged"));
    }

    # Bypass authenticated requests
    if (req.http.Authorization || req.http.Cookie ~ "adminhtml") {
        return (pass);
    }

    # Normalize Accept-Encoding header
    if (req.http.Accept-Encoding) {
        if (req.url ~ "\.(jpg|jpeg|png|gif|gz|tgz|bz2|tbz|mp3|ogg|swf|flv|ico)$") {
            # No point in compressing these
            unset req.http.Accept-Encoding;
        } elsif (req.http.Accept-Encoding ~ "gzip") {
            set req.http.Accept-Encoding = "gzip";
        } elsif (req.http.Accept-Encoding ~ "deflate") {
            set req.http.Accept-Encoding = "deflate";
        } else {
            # Unknown algorithm
            unset req.http.Accept-Encoding;
        }
    }

    # Remove all cookies for static files
    if (req.url ~ "^/(pub/)?(media|static)/") {
        unset req.http.Cookie;
        return (hash);
    }

    # Remove cookies for cacheable pages
    if (req.http.cookie) {
        if (req.url ~ "/catalog/product/gallery/id/") {
            unset req.http.Cookie;
            return(hash);
        }
        if (req.url ~ "^/(pub/)?(catalog|checkout|customer)") {
            return(pass);
        } else {
            # Remove all cookies except those needed for Magento functionality
            if (req.http.cookie !~ "X-Magento-Vary=") {
                unset req.http.Cookie;
            }
        }
    }

    # Normalize query strings
    set req.url = std.querysort(req.url);

    # Bypass non-cacheable HTTP methods
    if (req.method != "GET" &&
        req.method != "HEAD" &&
        req.method != "PUT" &&
        req.method != "POST" &&
        req.method != "TRACE" &&
        req.method != "OPTIONS" &&
        req.method != "DELETE") {
        return (pipe);
    }

    # Pass POST, PUT, PATCH, DELETE requests
    if (req.method == "POST" || req.method == "PUT" || req.method == "PATCH" || req.method == "DELETE") {
        return (pass);
    }

    # We only deal with GET and HEAD by default
    if (req.method != "GET" && req.method != "HEAD") {
        return (pass);
    }

    # Bypass caching for checkout and customer account
    if (req.url ~ "/checkout" || req.url ~ "/customer") {
        return (pass);
    }

    return (hash);
}

sub vcl_hash {
    if (req.http.cookie ~ "X-Magento-Vary=") {
        hash_data(regsub(req.http.cookie, "^.*?X-Magento-Vary=([^;]+);*.*$", "\1"));
    }

    # For multi-site configurations
    if (req.http.host) {
        hash_data(req.http.host);
    } else {
        hash_data(server.ip);
    }

    # Cache http and https separately
    if (req.http.X-Forwarded-Proto) {
        hash_data(req.http.X-Forwarded-Proto);
    }
}

sub vcl_backend_response {
    if (beresp.http.content-type ~ "text") {
        set beresp.do_esi = true;
    }

    # Set TTL based on cache-control headers
    if (bereq.url ~ "^/(pub/)?(media|static)/") {
        unset beresp.http.Pragma;
        unset beresp.http.Expires;
        set beresp.http.Cache-Control = "public, max-age=31536000";
        set beresp.ttl = 1y;
    } elseif (beresp.http.Cache-Control ~ "private") {
        set beresp.uncacheable = true;
        set beresp.ttl = 86400s;
        return (deliver);
    } elseif (beresp.status == 200 && bereq.url !~ "^/(pub/)?(media|static)/") {
        set beresp.ttl = 120s;
    } else {
        set beresp.ttl = 0s;
    }

    # Gzip compression
    if (beresp.http.content-type ~ "text/html" || beresp.http.content-type ~ "text/xml" || beresp.http.content-type ~ "text/plain" || beresp.http.content-type ~ "text/css" || beresp.http.content-type ~ "application/javascript" || beresp.http.content-type ~ "application/json") {
        set beresp.do_gzip = true;
    }

    # Add debug header to see if it's a HIT/MISS
    if (obj.hits > 0) {
        set beresp.http.X-Cache = "HIT";
        set beresp.http.X-Cache-Hits = obj.hits;
    } else {
        set beresp.http.X-Cache = "MISS";
    }

    return (deliver);
}

sub vcl_deliver {
    # Remove server headers for security
    unset resp.http.X-Powered-By;
    unset resp.http.Server;
    unset resp.http.X-Varnish;
    unset resp.http.Via;
    unset resp.http.Link;

    # Add cache debug headers
    if (obj.hits > 0) {
        set resp.http.X-Cache = "HIT";
        set resp.http.X-Cache-Hits = obj.hits;
    } else {
        set resp.http.X-Cache = "MISS";
    }

    return (deliver);
}

sub vcl_purge {
    return (synth(200, "Purged"));
}

sub vcl_init {
    return (ok);
}

sub vcl_fini {
    return (ok);
}