vcl 4.1;

import std;

backend default {
    .host = "wordpress";
    .port = "80";
    .first_byte_timeout = 600s;
}

acl purger {
    "localhost";
    "127.0.0.1";
    "0.0.0.0"
    "172.17.0.1";
}

sub vcl_recv {
    if (req.restarts > 0) {
        set req.hash_always_miss = true;
    }

    # return (pass);

    if (req.method == "PURGE") {
        if (client.ip !~ purger) {
            return (synth(405, "Method not allowed"));
        }
        if (req.http.X-Cache-Tags) {
            ban("obj.http.X-Cache-Tags ~ " + req.http.X-Cache-Tags);
        } else {
            ban("req.http.host == " + req.http.host + " && req.url ~ " + req.url);
            return (synth(200, "Purged"));
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
        # Non-RFC2616 or CONNECT which is weird.
        return (pipe);
    }

    # We only deal with GET and HEAD by default
    if (req.method != "GET" && req.method != "HEAD") {
        return (pass);
    }

    # Set initial grace period usage status
    set req.http.grace = "none";

    # Normalize URL in case of leading HTTP scheme and domain
    set req.url = regsub(req.url, "^http[s]?://", "");

    # Collect all cookies
    std.collect(req.http.Cookie);

    if (req.url ~ "^/admin/" || req.url ~ "/paypal/") {
        return (pass);
    }

    if (req.http.cookie ~ "wordpress_logged_in_") {
        return (pass);
    }

    if (req.http.Accept-Encoding) {
        if (req.url ~ "\\.(jpg|jpeg|png|gif|gz|tgz|bz2|tbz|mp3|ogg|swf|flv)$") {
            # No point in compressing these
            unset req.http.Accept-Encoding;
        } elsif (req.http.Accept-Encoding ~ "gzip") {
            set req.http.Accept-Encoding = "gzip";
        } elsif (req.http.Accept-Encoding ~ "deflate" && req.http.user-agent !~ "MSIE") {
            set req.http.Accept-Encoding = "deflate";
        } else {
            # Unknown algorithm
            unset req.http.Accept-Encoding;
        }
    }

    if (req.url ~ "(\\?|&)(gclid|cx|ie|cof|siteurl|zanpid|origin|fbclid|mc_[a-z]+|utm_[a-z]+|_bta_[a-z]+)=") {
        set req.url = regsuball(req.url, "(gclid|cx|ie|cof|siteurl|zanpid|origin|fbclid|mc_[a-z]+|utm_[a-z]+|_bta_[a-z]+)=[-_A-z0-9+()%.]+&?", "");
        set req.url = regsub(req.url, "[?|&]+$", "");
    }

    if (req.http.Authorization ~ "^Bearer") {
        return (pass);
    }

    return (hash);
}

sub vcl_hash {
    if (req.http.host) {
        hash_data(req.http.host);
    } else {
        hash_data(server.ip);
    }
    return (lookup);
}

sub vcl_backend_response {
    set beresp.grace = 3d;

    if (beresp.http.content-type ~ "text") {
        set beresp.do_esi = true;
    }

    if (beresp.http.content-type ~ "text") {
        set beresp.do_gzip = true;
    }

    # Cache only successful responses and 404s that are not marked as private
    if (beresp.status != 200 && beresp.status != 404 && beresp.http.Cache-Control ~ "private") {
        set beresp.uncacheable = true;
        set beresp.ttl = 86400s;
        return (deliver);
    }

    # Validate if we need to cache it and prevent from setting cookie
    if (beresp.ttl > 0s && (bereq.method == "GET" || bereq.method == "HEAD")) {
        unset beresp.http.set-cookie;
    }

    if (!beresp.http.cache-control) {
        set beresp.ttl = 0s;
        set beresp.uncacheable = true;
    }

    return (deliver);
}

sub vcl_deliver {
    set resp.http.X-Cache-Age = resp.http.Age;
    unset resp.http.Age;

    # Avoid being cached by the browser.
    if (resp.http.Cache-Control !~ "private") {
        set resp.http.Pragma = "no-cache";
        set resp.http.Expires = "-1";
        set resp.http.Cache-Control = "no-store, no-cache, must-revalidate, max-age=0";
    }

    unset resp.http.X-Powered-By;
    unset resp.http.Server;
    unset resp.http.X-Varnish;
    unset resp.http.Via;
    unset resp.http.Link;
    unset resp.http.X-Frame-Options;
    unset resp.http.X-Content-Type-Options;
    unset resp.http.X-Xss-Protection;
    unset resp.http.Referer-Policy;
    unset resp.http.X-Permitted-cross-domain-policies;

    return (deliver);
}

sub vcl_hit {
    if (obj.ttl >= 0s) {
        return (deliver);
    }
    set req.http.grace = "unlimited (unhealthy server)";
    return (deliver);
}