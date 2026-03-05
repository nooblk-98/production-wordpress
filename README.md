<div align="center">
  <img src="./images/logo.png" width="360" alt="WordPress Docker Stack logo" />

# Production oriented WordPress Docker stack with + Apache + Varnish + Caddy (Docker Compose) 
</div>

- **Varnish** (HTTP cache)
- **WordPress (Apache)**
- **MariaDB**
- **Caddy** (Reverse proxy + HTTPS)

## Documentation

- [Main Stack](README.md) - Core WordPress, Varnish, and MariaDB setup
- [Caddy Edge Proxy](caddy/README.md) - HTTPS termination, rate limiting, security headers
- [Varnish Cache](varnish/README.md) - Cache configuration, TTL, and bypass rules
- [PHP Configuration](php/custom.ini) - Production-optimized PHP settings

## 1) Quick start

1. clone :

   ```bash
   git clone https://github.com/nooblk-98/production-wordpress.git && cd production-wordpress
   ```

2. Copy env file:

   ```bash
   cp .env.example .env
   ```

   Create the shared Docker network (required for inter-service communication):

   ```bash
   docker network create wordpress-network
   ```

3. Edit `.env` with secure passwords.

4. Start stack:

   ```bash
   docker compose up -d
   ```

5. Open:

- `http://localhost`

## 2) Benefits of This Configuration

### Performance
- **Reduced server load**: 15-minute cache reduces WordPress/Apache processing by 90%+ on repeat visits
- **Faster page load**: Static assets and pages served from memory (no database queries)
- **High throughput**: Varnish can handle thousands of concurrent requests

### Reliability
- **Grace period (30 min)**: Serves cached content if WordPress backend is temporarily down
- **Automatic failover**: Users stay unaffected during WordPress maintenance or errors

### User Experience
- **Lightning-fast responses**: Cached content delivered in milliseconds
- **Smooth checkout**: Non-cached cart/checkout ensures real-time inventory updates
- **Fresh admin data**: Admin panel always bypasses cache for live updates

### Smart Caching
- **Logged-in users unaffected**: Each user gets their own uncached content
- **WooCommerce compatible**: Cart, checkout, and account pages always current
- **Admin protected**: `wp-admin` and `wp-login` never cached

### Cost Savings
- **Lower bandwidth**: Less database queries = lower server resource usage
- **Horizontal scaling ready**: Varnish can be load-balanced across multiple WordPress instances

## 3) PHP Configuration

The stack includes optimized PHP settings in `php/custom.ini` for production use:

### Performance Optimizations
- **OPcache**: Enabled with 128MB memory, caches compiled PHP code for faster execution
- **Realpath cache**: 4MB cache reduces filesystem lookups
- **Output buffering**: 4KB buffer improves response handling

### Security Hardening
- **Disabled functions**: Blocks dangerous functions (exec, shell_exec, system, etc.)
- **Session security**: HTTPOnly, Secure, and SameSite cookie protections
- **PHP version hidden**: `expose_php = Off` prevents version disclosure
- **Error handling**: Errors logged but not displayed to prevent information leakage

### Resource Limits
- **Upload size**: 64MB for media/plugin uploads
- **Memory limit**: 256MB for PHP processes
- **Execution time**: 300 seconds for long operations
- **Input variables**: 3000 for large forms/menus

### Customization
Edit `php/custom.ini` to adjust settings for your needs. For development environments, consider:
- Setting `display_errors = On`
- Setting `opcache.validate_timestamps = 1`
- Removing the `disable_functions` line

Changes take effect after restarting the WordPress container:
```bash
docker compose restart wordpress
```

## 4) Caddy Edge Proxy (Optional)

For HTTPS/TLS and additional reverse proxy features, deploy Caddy from the `caddy/` folder:

1. Create the shared Docker network (required for inter-service communication):
   ```bash
   docker network create wordpress-network
   ```

2. In the `caddy/` folder, create `.env`:
   ```bash
   cp .env.example .env
   ```

3. Update `.env` with your domain(s) and email for SSL certificates.

4. Start Caddy:
   ```bash
   cd caddy && docker compose up -d
   ```

**Note:** The main WordPress stack must be running first. Both stacks communicate via the `wordpress-network` Docker network.

## 5) Optional tools

Add your preferred DB admin tool only when needed, or connect directly with a local SQL client.

## 6) Notes

- Varnish bypasses cache for logged-in/admin/cart/checkout style requests.
- Data persists in named volumes: `db_data`, `wp_data`.

## 7) Suggested next improvements

1. **Monitoring**: add uptime checks and container metrics (e.g., Uptime Kuma + Prometheus/Grafana).
