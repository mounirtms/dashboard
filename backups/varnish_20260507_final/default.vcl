# Varnish VCL for Multi-Site Setup
vcl 4.0;

import std;

backend default {
    .host = "127.0.0.1";
    .port = "81";
    .first_byte_timeout = 600s;
    .connect_timeout = 600s;
    .between_bytes_timeout = 60s;
}

acl purge {
    "localhost";
    "127.0.0.1";
    "::1";
    "205.134.249.177";
}

sub vcl_recv {
    if (req.restarts > 0) {
        set req.hash_always_miss = true;
    }

    set req.http.X-Forwarded-Proto = "https";
    set req.http.host = regsub(req.http.host, ":[0-9]+", "");

    if (req.http.user-agent ~ "(?i)(iphone|ipod|android.*mobile|windows phone|blackberry|opera mini|iemobile)") {
        set req.http.X-UA-Device = "mobile";
    } elsif (req.http.user-agent ~ "(?i)(ipad|android(?!.*mobile)|playbook|silk|tablet)") {
        set req.http.X-UA-Device = "tablet";
    } else {
        set req.http.X-UA-Device = "desktop";
    }

    if (req.method == "PURGE") {
        if (!client.ip ~ purge) {
            return (synth(405, "Not allowed"));
        }
        if (req.http.X-Magento-Tags-Pattern) {
            ban("obj.http.X-Magento-Tags ~ " + req.http.X-Magento-Tags-Pattern);
        }
        return (synth(200, "Purged"));
    }

    if (req.http.host ~ "^dashboard\.technostationery\.com") {
        return (pass);
    }

    if (req.http.host ~ "^pim\.technostationery\.com") {
        if (req.url ~ "^/(user/login|admin|_wdt|_profiler|api)" || req.method != "GET" && req.method != "HEAD") {
            return (pass);
        }
        unset req.http.Cookie;
        return (hash);
    }

    if (req.http.host ~ "(technostationery\.com|beta\.technostationery\.com|dev\.technostationery\.com)") {
        if (req.url ~ "^/(sysadminy|checkout|customer|wishlist|catalogsearch|contact/index/post|newsletter/subscriber/new)") {
            return (pass);
        }
        if (req.method != "GET" && req.method != "HEAD") {
            return (pass);
        }
        if (req.http.Cookie ~ "(persistent|frontend|adminhtml|X-Magento-Vary)") {
            return (pass);
        }
        set req.http.Cookie = regsuball(req.http.Cookie, "(^|;\s*)(_ga|_gid|_gat|__utm[a-z]|_hj[a-z])=[^;]*", "");
        set req.http.Cookie = regsuball(req.http.Cookie, "^;\s*", "");
        if (req.http.Cookie == "") {
            unset req.http.Cookie;
        }
        if (req.url ~ "^/(pub/)?(static|media|assets)/") {
            unset req.http.Cookie;
        }
        return (hash);
    }

    return (pass);
}

sub vcl_hash {
    hash_data(req.url);
    hash_data(req.http.host);
    if (req.http.X-UA-Device) {
        hash_data(req.http.X-UA-Device);
    }
    hash_data(req.http.X-Forwarded-Proto);
}

sub vcl_backend_fetch {
    set bereq.http.X-Forwarded-Proto = "https";
    
    if (bereq.http.host ~ "^(www\.)?technostationery\.com") {
        set bereq.http.host = "technostationery.com";
    } elsif (bereq.http.host ~ "^(www\.)?beta\.technostationery\.com") {
        set bereq.http.host = "beta.technostationery.com";
    } elsif (bereq.http.host ~ "^dev\.technostationery\.com") {
        set bereq.http.host = "dev.technostationery.com";
    }
    
    # Let Apache's .htaccess handle / to /pub/ rewrite
    # Do NOT add /pub prefix here - Apache will handle it via .htaccess
}

sub vcl_backend_response {
    set beresp.grace = 7d;
    if (bereq.url ~ "^/(pub/)?(static|media)/") {
        set beresp.ttl = 30d;
        set beresp.grace = 60d;
        unset beresp.http.set-cookie;
    }
    if (beresp.http.content-type ~ "text/html") {
        if (bereq.url ~ "\.html$" && bereq.url !~ "/index\.html") {
            set beresp.ttl = 12h;
            set beresp.grace = 14d;
        } else {
            set beresp.ttl = 4h;
            set beresp.grace = 7d;
        }
        unset beresp.http.set-cookie;
        unset beresp.http.pragma;
        unset beresp.http.cache-control;
    }
    if (beresp.status >= 500) {
        set beresp.ttl = 0s;
    }
    return (deliver);
}

sub vcl_deliver {
    if (obj.hits > 0) {
        set resp.http.X-Cache = "HIT";
        set resp.http.X-Cache-Hits = obj.hits;
    } else {
        set resp.http.X-Cache = "MISS";
    }
    set resp.http.X-UA-Device = req.http.X-UA-Device;
    unset resp.http.X-Magento-Debug;
    unset resp.http.X-Magento-Tags;
    unset resp.http.X-Powered-By;
    unset resp.http.Server;
    unset resp.http.X-Varnish;
    unset resp.http.Via;
    unset resp.http.Link;
}
