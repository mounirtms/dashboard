vcl 4.1;

# Default backend
backend default {
    .host = "127.0.0.1";
    .port = "80";
    .connect_timeout = 5s;
    .first_byte_timeout = 60s;
    .between_bytes_timeout = 10s;
    .max_connections = 300;
}

# ============================================
# DASHBOARD CACHE POLICY
# ============================================
sub vcl_recv {
    # Dashboard - never cache authenticated routes
    if (req.url ~ "^/user/") {
        return (pass);
    }
    if (req.url ~ "^/api/") {
        return (pass);
    }
    
    # Static assets for dashboard - cache
    if (req.url ~ "\.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot)(\?.*)?$") {
        set req.url = regsub(req.url, "\?.*$", "");
    }
    
    # Clean tracking cookies
    set req.http.Cookie = regsuball(req.http.Cookie, "_ga=[^;]+(; )?", "");
    set req.http.Cookie = regsuball(req.http.Cookie, "_gid=[^;]+(; )?", "");
    set req.http.Cookie = regsuball(req.http.Cookie, "_gat=[^;]+(; )?", "");
    if (req.http.Cookie == "") {
        unset req.http.Cookie;
    }
    
    # Static files - cache without cookies
    if (req.url ~ "\.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot|pdf|mp4|webm)$") {
        unset req.http.Cookie;
        return (hash);
    }
    
    # Authenticated routes - pass
    if (req.http.Cookie ~ "frontend=|adminhtml=|PHPSESSID=") {
        return (pass);
    }
    
    return (hash);
}

sub vcl_backend_response {
    # Static assets - long cache (7 days)
    if (bereq.url ~ "\.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot)$") {
        set beresp.ttl = 7d;
        set beresp.http.Cache-Control = "public, max-age=604800";
        unset beresp.http.Set-Cookie;
    }
    
    # Never cache user/login and API routes
    if (bereq.url ~ "^/user/") {
        set beresp.ttl = 0s;
        return (deliver);
    }
    if (bereq.url ~ "^/api/") {
        set beresp.ttl = 0s;
        return (deliver);
    }
    
    # HTML pages with grace
    if (beresp.http.content-type ~ "text/html") {
        set beresp.ttl = 5m;
        set beresp.http.Cache-Control = "public, max-age=300";
        set beresp.grace = 1h;
    }
    
    # Error pages - short cache
    if (beresp.status >= 500) {
        set beresp.ttl = 0s;
        set beresp.grace = 15s;
        return (deliver);
    }
    
    set beresp.grace = 1h;
    return (deliver);
}

sub vcl_deliver {
    # Add cache headers for debugging
    if (obj.hits > 0) {
        set resp.http.X-Cache = "HIT";
    } else {
        set resp.http.X-Cache = "MISS";
    }
    
    # Remove internal headers
    unset resp.http.X-Generator;
    
    return (deliver);
}