vcl 4.1;

backend default {
    .host = "wordpress";
    .port = "80";
    .connect_timeout = 5s;
    .first_byte_timeout = 120s;
    .between_bytes_timeout = 60s;
}

acl purge {
    "localhost";
    "127.0.0.1";
    "::1";
}

sub vcl_init {
    # Graceful shutdown: allow 30s for ongoing requests to complete
    # Varnish will drain connections during shutdown
}

sub vcl_recv {
    if (req.method == "PURGE") {
        if (!client.ip ~ purge) {
            return (synth(405, "Not allowed."));
        }
        return (purge);
    }

    if (req.method != "GET" && req.method != "HEAD") {
        return (pass);
    }

    if (req.url ~ "wp-admin|wp-login|preview=true|xmlrpc.php|/cart|/checkout|/my-account|wc-api") {
        return (pass);
    }

    if (req.http.Authorization || req.http.Cookie ~ "wordpress_logged_in_|comment_author|woocommerce_items_in_cart|woocommerce_cart_hash") {
        return (pass);
    }

    unset req.http.Cookie;
    return (hash);
}

sub vcl_backend_response {
    if (bereq.url ~ "wp-admin|wp-login|preview=true|xmlrpc.php") {
        set beresp.uncacheable = true;
        set beresp.ttl = 0s;
        return (deliver);
    }

    if (beresp.http.Set-Cookie) {
        set beresp.uncacheable = true;
        set beresp.ttl = 0s;
        return (deliver);
    }

    set beresp.ttl = 15m;
    set beresp.grace = 30m;
}

sub vcl_backend_error {
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
    
    if (resp.is_streaming) {
        return (deliver);
    }
}
