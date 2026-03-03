# WordPress + Apache + Varnish (Docker Compose)

Production-oriented Docker stack:

- **Varnish** (HTTP cache)
- **WordPress (Apache)**
- **MariaDB**

## 1) Quick start

1. Copy env file:

   ```bash
   cp .env.example .env
   ```

   On Windows PowerShell:

   ```powershell
   Copy-Item .env.example .env
   ```

2. Edit `.env` with secure passwords.

3. Start stack:

   ```bash
   docker compose up -d
   ```

4. Open:

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

## 3) Optional tools

Add your preferred DB admin tool only when needed, or connect directly with a local SQL client.

## 4) Notes

- Varnish bypasses cache for logged-in/admin/cart/checkout style requests.
- Data persists in named volumes: `db_data`, `wp_data`.

## 5) Suggested next improvements

1. **Backups**: add scheduled DB dump + `wp_data` snapshot (daily, offsite).
2. **Redis object cache**: add Redis container and WordPress Redis plugin.
3. **TLS reverse proxy**: add Caddy or Nginx Proxy Manager when moving to production HTTPS.
4. **WAF/CDN**: place Cloudflare or similar in front for DDoS and WAF.
5. **Monitoring**: add uptime checks and container metrics (e.g., Uptime Kuma + Prometheus/Grafana).
