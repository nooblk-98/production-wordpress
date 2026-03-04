FROM caddy:2-builder AS builder

RUN xcaddy build \
    --with github.com/mholt/caddy-ratelimit \
    --with github.com/caddyserver/prometheus

FROM caddy:2

COPY --from=builder /usr/bin/caddy /usr/bin/caddy
