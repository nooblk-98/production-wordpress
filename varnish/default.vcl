vcl 4.1;

backend default {
    .host = "wordpress";
    .port = "80";
    .max_connections = 100;
    .connect_timeout = 10s;
    .first_byte_timeout = 120s;
    .between_bytes_timeout = 60s;
}

acl purge {
    "localhost";
    "127.0.0.1";
    "::1";
}

sub vcl_recv {

    # Allow PURGE from local only
    if (req.method == "PURGE") {
        if (!client.ip ~ purge) {
            return (synth(405, "Not allowed."));
        }
        return (purge);
    }

    # Never cache POST/PUT/DELETE/etc
    if (req.method != "GET" && req.method != "HEAD") {
        return (pass);
    }

    # ===== BYPASS FOR H3K FILE MANAGER =====
    if (req.url ~ "^/files\.php") {
        return (pass);
    }

    # ===== BYPASS WORDPRESS DYNAMIC =====
    if (req.url ~ "wp-admin|wp-login|preview=true|xmlrpc.php|/cart|/checkout|/my-account|wc-api") {
        return (pass);
    }

    # ===== BYPASS IF AUTH OR IMPORTANT COOKIES =====
    if (req.http.Authorization ||
        req.http.Cookie ~ "wordpress_logged_in_" ||
        req.http.Cookie ~ "comment_author" ||
        req.http.Cookie ~ "woocommerce_items_in_cart" ||
        req.http.Cookie ~ "woocommerce_cart_hash" ||
        req.http.Cookie ~ "PHPSESSID") {
        return (pass);
    }

    # Remove cookies for static caching
    unset req.http.Cookie;

    return (hash);
}

sub vcl_backend_response {

    # Retry transient upstream failures first (helps cold first-load bursts)
    if (beresp.status == 502 || beresp.status == 503 || beresp.status == 504) {
        if (bereq.retries < 2) {
            return (retry);
        }
        set beresp.uncacheable = true;
        set beresp.ttl = 0s;
        return (deliver);
    }

    # Never cache backend errors
    if (beresp.status >= 500) {
        set beresp.uncacheable = true;
        set beresp.ttl = 0s;
        return (deliver);
    }

    # Never cache file manager
    if (bereq.url ~ "^/files\.php") {
        set beresp.uncacheable = true;
        set beresp.ttl = 0s;
        return (deliver);
    }

    # Never cache WP admin/login
    if (bereq.url ~ "wp-admin|wp-login|preview=true|xmlrpc.php") {
        set beresp.uncacheable = true;
        set beresp.ttl = 0s;
        return (deliver);
    }

    # If backend sets cookies → don't cache
    if (beresp.http.Set-Cookie) {
        set beresp.uncacheable = true;
        set beresp.ttl = 0s;
        return (deliver);
    }

    # Static assets: keep hot and serve stale if backend is unstable
    if (bereq.url ~ "(?i)\.(css|js|jpg|jpeg|png|gif|webp|svg|ico|woff2?|ttf|eot|mp4|webm|avif)(\?.*)?$") {
        unset beresp.http.Set-Cookie;
        set beresp.ttl = 24h;
        set beresp.grace = 72h;
        set beresp.keep = 24h;
        return (deliver);
    }

    # Default cache
    set beresp.ttl = 15m;
    set beresp.grace = 30m;
}

sub vcl_backend_error {
    if (bereq.retries < 2) {
        return (retry);
    }

    if (bereq.uncacheable) {
        return (deliver);
    }

    set beresp.ttl = 1m;
    set beresp.grace = 30m;
    return (deliver);
}

sub vcl_deliver {

    if (obj.hits > 0) {
        set resp.http.X-Cache = "HIT";
    } else {
        set resp.http.X-Cache = "MISS";
    }

    return (deliver);
}