# Caddy Edge Proxy

**HTTPS reverse proxy with automatic Let's Encrypt SSL certificates and security headers.**

**Part of:** [Production WordPress Stack](../README.md) | [Varnish Cache](../varnish/README.md)

## Overview

Caddy acts as an edge proxy sitting in front of your WordPress + Varnish stack:

```
Internet (HTTPS) → Caddy → Varnish Cache → WordPress (HTTP)
```

**Features:**
- ✅ Automatic HTTPS with Let's Encrypt (free SSL)
- ✅ Security headers (X-Frame-Options, HSTS, etc.)
- ✅ XML-RPC blocking (brute force protection)
- ✅ HTTP compression (zstd, gzip)
- ✅ Request body size limit (25MB default)
- ✅ Official Caddy latest image (minimal, fast, reliable)

---

## Quick Start

### 1. Prerequisites

Ensure the main WordPress stack is running:
```bash
# From main directory
docker network create wordpress-network
docker compose up -d

# Verify WordPress is accessible
curl -i http://localhost:8080
```

### 2. Set Up Caddy

```bash
cd caddy
cp .env.example .env
```

### 3. Configure Domain

Edit `caddy/.env`:

```env
# Single domain
SITE_HOST=example.com

# OR multiple domains (space-separated)
SITE_HOST=www.example.com example.com

# Your email for Let's Encrypt notifications
CADDY_EMAIL=admin@example.com
```

### 4. Start Caddy

```bash
docker compose up -d

# Verify it's running
docker ps | grep caddy
curl -i https://example.com
```

### 5. Test HTTPS

```bash
# Should see "200 OK" with HTTPS
curl -i https://example.com
```

---

## Features

### Automatic HTTPS

- **Automatic**: Obtains free SSL with Let's Encrypt
- **Auto-renewal**: Renews certificates before expiration
- **Multi-domain**: Supports multiple domains in one certificate
- **HTTP → HTTPS**: Automatically redirects insecure requests

### Security Headers

Automatically added to all responses:

| Header | Purpose |
|--------|---------|
| `X-Frame-Options: SAMEORIGIN` | Prevents clickjacking |
| `X-Content-Type-Options: nosniff` | Prevents MIME type sniffing |
| `Strict-Transport-Security: max-age=31536000` | Forces HTTPS |
| `Permissions-Policy` | Disables camera, microphone, geolocation |
| `Referrer-Policy: strict-origin-when-cross-origin` | Controls referrer info |

### XML-RPC Blocking

Blocks WordPress XML-RPC endpoint at `/xmlrpc.php`:
- Prevents brute force password attempts
- Blocks pingback spam
- Returns HTTP 403 Forbidden

### HTTP Compression

Reduces page size 70-90%:
- **zstd** - Modern browsers (best compression)
- **gzip** - Fallback for older browsers
- Automatic negotiation based on client support

### Request Body Limit

- **Default**: 25MB max request body
- **Purpose**: Prevents buffer overflow attacks
- **Adjust** in `.env`:
  ```env
  MAX_BODY_SIZE=100MB
  ```

---

## Configuration

### Environment Variables (`.env`)

```env
# Domain(s) to serve - space-separated for multiple
SITE_HOST=www.example.com example.com

# Email for Let's Encrypt notifications
CADDY_EMAIL=admin@example.com

# Request body size limit
MAX_BODY_SIZE=25MB

# Backend upstream (don't change - points to Varnish)
UPSTREAM=wp_varnish:6081
```

---

## Docker Commands

```bash
# View Caddy logs
docker compose logs -f caddy

# Check for errors
docker compose logs caddy | grep ERROR

# Restart Caddy
docker compose restart caddy

# Connect to Caddy container
docker exec -it caddy sh

# Test configuration
docker exec caddy caddy validate
```

---

## Monitoring & Troubleshooting

### Certificate Issues

```bash
# Check certificate status
docker compose logs caddy | grep "certificate\|Let's Encrypt"

# Verify ports are open
sudo lsof -i :80
sudo lsof -i :443
```

### "No Upstreams Available"

WordPress stack isn't running:

```bash
# Check WordPress is running
docker ps | grep wordpress

# Check network exists
docker network ls | grep wordpress-network

# Go to main directory and restart
cd .. && docker compose up -d
```

### Connection Timeouts

WordPress/Varnish backend is slow:

1. Check resource usage:
   ```bash
   docker stats wordpress varnish
   ```

2. Check WordPress logs:
   ```bash
   docker compose logs wordpress | tail -50
   ```

3. Verify WordPress is responding:
   ```bash
   curl -i http://localhost:8080
   ```

---

## Network Architecture

Caddy and WordPress communicate through Docker's `wordpress-network`:

```
┌─────────────────────────┐
│ Internet (HTTPS:443)    │
└──────────────┬──────────┘
               │
┌──────────────▼──────────────────┐
│ Caddy (port 80, 443)            │ ← HTTPS termination
│ Rate limiting, security headers │
└──────────────┬──────────────────┘
               │ (Docker network)
┌──────────────▼──────────────────┐
│ Varnish Cache                   │ ← Cache layer
│ (internal :6081)                │
└──────────────┬──────────────────┘
               │
┌──────────────▼──────────────────┐
│ WordPress + Apache              │ ← Application
│ (internal :8080)                │
└─────────────────────────────────┘
```

---

## Best Practices

✅ **Do:**
- Use Caddy for production (automatic SSL is critical)
- Keep security headers enabled
- Monitor certificate renewal in logs
- Adjust request body limit based on your file upload needs

❌ **Don't:**
- Disable security headers
- Expose WordPress directly without HTTPS
- Set MAX_BODY_SIZE too high (1GB+)
- Run without Let's Encrypt renewal checks

---

## Performance Tips

- Caddy compression reduces bandwidth 70-90%
- Connection pooling reuses connections to backend
- Always use HTTPS for production (required for modern browsers)
- XML-RPC blocking reduces brute force attacks by 99%

---

For main stack configuration, see [Production WordPress README](../README.md).
