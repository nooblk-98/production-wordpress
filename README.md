# WordPress + Apache + Varnish + Caddy (Docker Compose)

Production-oriented Docker stack:

- **Caddy** (TLS termination / reverse proxy)
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

2. Edit `.env` with secure passwords and your domain/email.

3. Start stack:

   ```bash
   docker compose up -d
   ```

4. Open:

- `http://localhost` (or your domain in production)

## 2) Optional tools

Add your preferred DB admin tool only when needed, or connect directly with a local SQL client.

## 3) Notes

- Varnish bypasses cache for logged-in/admin/cart/checkout style requests.
- Caddy handles HTTPS automatically when `SITE_ADDRESS` is a real public domain and DNS points to this server.
- Data persists in named volumes: `db_data`, `wp_data`, `caddy_data`, `caddy_config`.

## 4) Suggested next improvements

1. **Backups**: add scheduled DB dump + `wp_data` snapshot (daily, offsite).
2. **Redis object cache**: add Redis container and WordPress Redis plugin.
3. **WAF/CDN**: place Cloudflare or similar in front for DDoS and WAF.
4. **Monitoring**: add uptime checks and container metrics (e.g., Uptime Kuma + Prometheus/Grafana).
5. **Secrets**: move sensitive values to Docker secrets or external secret manager.
