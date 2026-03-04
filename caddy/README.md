# Standalone Caddy Edge Proxy

This folder contains a separate Docker Compose stack for Caddy with:

- **Custom Caddy build** with Rate Limiting & Prometheus plugins
- Security headers
- Request body size limit
- Reverse proxy to your existing WordPress/Varnish stack
- Built-in rate limiting (100 req/min per IP)
- Prometheus metrics endpoint

## Quick start

1. Copy env file:

   - PowerShell: `Copy-Item .env.example .env`
   - Bash: `cp .env.example .env`

2. Build custom Caddy image:

   - `docker compose build`

3. Start Caddy stack:

   - `docker compose up -d`

4. Open:

   - HTTP: `http://localhost:8080`
   - HTTPS: `https://localhost:8443`
   - Metrics: `http://localhost:2019/metrics`

## Important for your current setup

- Your main stack currently already binds host port `80` with Varnish.
- This Caddy stack defaults to `8080/8443` to avoid port conflicts.

## Plugins Included

### Rate Limiting
- **Default**: 100 requests per minute per IP
- Protects against brute force and abuse
- Configured in `Caddyfile` under `rate_limit` directive

To adjust limits, edit `Caddyfile`:
```
rate_limit {
    zone dynamic_zone {
        key {remote_host}
        events 100      # Max requests
        window 1m       # Time window
    }
}
```

### Prometheus Metrics
- **Endpoint**: `http://localhost:2019/metrics`
- Exports Caddy performance metrics
- Compatible with Prometheus/Grafana monitoring

Add to Prometheus `scrape_configs`:
```yaml
- job_name: 'caddy'
  static_configs:
    - targets: ['caddy:2019']
```

## Security recommendations

1. **Rate limiting enabled**: 100 req/min per IP (adjust in Caddyfile if needed)
2. Keep XML-RPC blocked unless explicitly required.
3. Use strong WordPress admin passwords + MFA plugin.
4. Keep plugin/theme updates automatic where possible.
5. Put CDN/WAF (Cloudflare, etc.) in front for DDoS and bot filtering.
6. Monitor metrics endpoint for unusual traffic patterns.

## Monitoring

View real-time metrics:
```bash
curl http://localhost:2019/metrics
```

Check Caddy logs:
```bash
docker compose logs -f caddy
```
