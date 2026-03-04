# Standalone Caddy Edge Proxy

This folder contains a separate Docker Compose stack for Caddy with:

- **Custom Caddy build** with Rate Limiting plugin
- Security headers
- Request body size limit
- Reverse proxy to your existing WordPress/Varnish stack
- Built-in rate limiting (100 req/min per IP)

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

## Important for your current setup

- Your main stack currently already binds host port `80` with Varnish.
- This Caddy stack defaults to `8080/8443` to avoid port conflicts.

## Domain Configuration

You can configure one or multiple domains in `.env`:

### Single Domain
```env
SITE_HOST=example.com
```

### Multiple Domains (recommended)
```env
SITE_HOST=www.example.com example.com
```

This setup will serve both `www.example.com` and `example.com` with the same configuration, SSL certificate, and reverse proxy to WordPress/Varnish.

**Auto HTTPS:** Caddy automatically obtains and renews SSL certificates from Let's Encrypt for all domains listed.

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
- **Use Node Exporter** for system-level metrics (CPU, memory, disk, network)
- **Install Node Exporter** on your host or as a Docker container
- Add to your Prometheus `scrape_configs`:

```yaml
- job_name: 'node'
  static_configs:
    - targets: ['node-exporter:9100']
```

**Why Node Exporter instead of Caddy metrics?**
- Provides comprehensive system monitoring (not just Caddy)
- Works across all containers in your stack
- Industry standard for infrastructure monitoring
- Lighter weight than Caddy metrics endpoint

## Security recommendations

1. **Rate limiting enabled**: 100 req/min per IP (adjust in Caddyfile if needed)
2. Keep XML-RPC blocked unless explicitly required.
3. Use strong WordPress admin passwords + MFA plugin.
4. Keep plugin/theme updates automatic where possible.
5. Put CDN/WAF (Cloudflare, etc.) in front for DDoS and bot filtering.
6. Monitor Node Exporter for unusual system metrics.

## Monitoring

Check Caddy logs:
```bash
docker compose logs -f caddy
```

### Setup Node Exporter

Add to main stack's `docker-compose.yml`:
```yaml
  node-exporter:
    image: prom/node-exporter:latest
    container_name: node_exporter
    restart: unless-stopped
    command:
      - '--path.rootfs=/host'
      - '--path.procfs=/host/proc'
      - '--path.sysfs=/host/sys'
      - '--collector.filesystem.mount-points-exclude=^/(sys|proc|dev|host|etc)($$|/)'
    volumes:
      - /:/host:ro,rsw
    ports:
      - "9100:9100"
    networks:
      - wp_net
```
