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

## 2) Optional tools

Add your preferred DB admin tool only when needed, or connect directly with a local SQL client.

## 3) Notes

- Varnish bypasses cache for logged-in/admin/cart/checkout style requests.
- Data persists in named volumes: `db_data`, `wp_data`.

## 4) Suggested next improvements

1. **Backups**: add scheduled DB dump + `wp_data` snapshot (daily, offsite).
2. **Redis object cache**: add Redis container and WordPress Redis plugin.
3. **TLS reverse proxy**: add Caddy or Nginx Proxy Manager when moving to production HTTPS.
4. **WAF/CDN**: place Cloudflare or similar in front for DDoS and WAF.
5. **Monitoring**: add uptime checks and container metrics (e.g., Uptime Kuma + Prometheus/Grafana).
