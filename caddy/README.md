# Caddy Edge Proxy

This folder contains a separate Docker Compose stack for Caddy with:

- **Prebuild Caddy image** (`lahiru98s/caddy-extended`) with Rate Limiting plugin included
- Security headers (X-Frame-Options, X-Content-Type-Options, Strict-Transport-Policy, etc.)
- Request body size limit
- Reverse proxy to your existing WordPress/Varnish stack
- Built-in rate limiting (100 req/min per IP)
- Automatic HTTPS via Let's Encrypt

**Part of:** [Production WordPress Stack](../README.md) | [Varnish Cache](../varnish/README.md)

## Quick start

1. Ensure the main WordPress stack is running and the shared network is created:
   ```bash
   docker network create wordpress-network
   ```

2. Copy env file:

   - PowerShell: `Copy-Item .env.example .env`
   - Bash: `cp .env.example .env`

3. Edit `.env` with your domain(s) and SSL email.

4. Start Caddy stack:

   ```bash
   docker compose up -d
   ```

5. Open your domain with HTTPS enabled.

## Important for your current setup

- Caddy binds to host ports **80 and 443** (standard HTTP/HTTPS ports).
- The main WordPress/Varnish stack is accessed internally via the `wordpress-network`.
- Varnish port `:8080` is only exposed internally on the Docker network, not to the host.

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

## Features

### 1. Rate Limiting
- **Enabled by default**: 100 requests per minute per IP
- **Disable**: Set `RATE_LIMIT_ENABLED=false` in `.env` and comment out the `rate_limit` block in `Caddyfile`
- Protects against brute force attacks and DDoS attempts
- Configured per IP address (remote host)
- Adjust in `.env`:
  ```env
  RATE_LIMIT_EVENTS=100      # Max requests
  RATE_LIMIT_WINDOW=1m       # Time window
  ```

### 2. Security Headers
Automatically adds security headers to all responses:
- `X-Frame-Options: SAMEORIGIN` - Prevents clickjacking
- `X-Content-Type-Options: nosniff` - Prevents MIME type sniffing
- `Referrer-Policy: strict-origin-when-cross-origin` - Controls referrer info
- `Permissions-Policy` - Disables camera, microphone, geolocation
- `Strict-Transport-Security: max-age=31536000; includeSubDomains; preload` - Forces HTTPS

### 3. XML-RPC Blocking
Blocks dangerous XML-RPC endpoint at `/xmlrpc.php`:
- Prevents brute force attacks on WordPress
- Blocks pingback spam
- Returns HTTP 403 Forbidden

### 4. Request Body Size Limit
- **Default**: 25MB max request body size (configurable via `MAX_BODY_SIZE` in `.env`)
- Protects against buffer overflow attacks
- Prevents large file uploads that could crash the server

### 5. HTTP Compression
Automatically compresses responses with:
- **zstd** - Best compression ratio (modern browsers)
- **gzip** - Fallback for older browsers
- Reduces bandwidth usage by 70-90%

### 6. Automatic HTTPS
- Obtains free SSL/TLS certificates from Let's Encrypt
- Auto-renews before expiration
- Supports multiple domains
- HTTP to HTTPS redirects automatically

### 7. Reverse Proxy
- Routes all traffic to WordPress/Varnish backend
- Connection pooling for better performance
- Configurable timeout settings:
  - Read/Write timeout: 30s
  - Dial timeout: 5s

### 8. Logging
- Full access logs in console format
- Easy debugging with structured logging
- View logs: `docker compose logs -f caddy`

## Configuration

### Environment Variables (`.env`)

```env
# Your domain(s) - space-separated for multiple domains
SITE_HOST=www.example.com example.com

# Email for Let's Encrypt SSL certificate notifications
CADDY_EMAIL=admin@example.com

# Maximum request body size (default: 25MB)
MAX_BODY_SIZE=25MB

# Rate Limiting Configuration
RATE_LIMIT_ENABLED=true              # Enable/disable rate limiting
RATE_LIMIT_EVENTS=100                # Max requests per window
RATE_LIMIT_WINDOW=1m                 # Time window (1m, 10s, etc.)

# Backend upstream (normally wp_varnish:6081)
UPSTREAM=wp_varnish:6081
```

### Disable Rate Limiting

To completely disable rate limiting:

1. Set in `.env`:
   ```env
   RATE_LIMIT_ENABLED=false
   ```

2. Comment out the `rate_limit` block in `Caddyfile`:
   ```
   # rate_limit {
   #     zone dynamic_zone {
   #         key {remote_host}
   #         events {$RATE_LIMIT_EVENTS:100}
   #         window {$RATE_LIMIT_WINDOW:1m}
   #     }
   # }
   ```

3. Reload Caddy:
   ```bash
   docker compose down && docker compose up -d
   ```

### Auto-Adjust Rate Limiting

### Adjust Body Size Limit

Update `.env`:
```env
MAX_BODY_SIZE=100MB
```

Or directly in `Caddyfile`:
```
request_body {
    max_size 100MB
}
```

## Monitoring

### View Logs
```bash
docker compose logs -f caddy
```

Check for errors:
```bash
docker compose logs caddy | grep ERROR
```

### System Metrics

Use Node Exporter for system-level monitoring (CPU, memory, disk, network):

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
      - wordpress-network
```

Add to Prometheus `scrape_configs`:
```yaml
- job_name: 'node'
  static_configs:
    - targets: ['node-exporter:9100']
```

## Security Best Practices

1. ✅ **Rate limiting enabled** - 100 req/min per IP (adjust if needed)
2. ✅ **XML-RPC blocked** - Prevents brute force and pingback spam
3. ✅ **Security headers** - All modern headers configured
4. ✅ **HTTPS enforced** - Automatic Let's Encrypt certificates
5. ✅ **Body size limited** - Prevents buffer overflow attacks
6. ⚠️ **Use strong WordPress passwords** - MFA plugin recommended
7. ⚠️ **Keep updates current** - Enable auto-updates for plugins/themes
8. ⚠️ **Add CDN/WAF** - Place Cloudflare or Bunny WAF in front for additional DDoS protection

## Troubleshooting

### "no upstreams available" error
- Ensure WordPress stack is running: `docker ps | grep wp_`
- Verify network exists: `docker network ls | grep wordpress-network`
- Check WordPress container is healthy: `docker compose logs wp_app`

### Certificate renewal failing
- Ensure port 443 is accessible from the internet
- Check certificate logs: `docker compose logs caddy | grep certificate`
- Rate limiting may need adjustment if Let's Encrypt tries too frequently

### High rate limit rejections?
- Increase limit in `.env` or `Caddyfile`
- Check if legitimate traffic is being blocked: `docker compose logs caddy | grep "rate_limit"`
- Whitelist trusted IPs if needed (edit `Caddyfile`)

### Connection timeouts
- Increase timeout values in `Caddyfile` if backend is slow
- Check WordPress/Varnish performance
- Monitor resource usage with Node Exporter

## Performance Tips

1. **Cache-friendly headers** - Ensure Varnish caching rules work with Caddy
2. **Compression** - zstd/gzip enabled by default, reduces bandwidth 70-90%
3. **Connection pooling** - Caddy reuses connections to backend
4. **Rate limiting** - Prevents resource exhaustion from abuse
