<div align="center">
  <img src="./images/logo.png" width="360" alt="WordPress Docker Stack logo" />

# Production WordPress Docker Stack

**Apache + Varnish Cache + MariaDB + Caddy (HTTPS)**

</div>

## Stack Components

- **Varnish** - HTTP caching layer with auto-purge WordPress plugin
- **WordPress (Apache)** - PHP 8.2, optimized for production
- **MariaDB** - Database with replication support
- **Caddy** - Reverse proxy with automatic HTTPS (optional)

## Key Features

✅ **Smart Caching** - Varnish with 15-min default TTL, auto-purge on content updates  
✅ **One-Click Purge** - WordPress admin panel purge buttons (Tools → Varnish Purge)  
✅ **Auto-scaling** - Apache MPM limited to 4 concurrent processes  
✅ **Production-Ready** - Security hardening, error logging, session protection  
✅ **HTTPS Ready** - Optional Caddy edge proxy with Let's Encrypt  

---

## Quick Start

### 1. Clone Repository

```bash
git clone https://github.com/nooblk-98/production-wordpress.git
cd production-wordpress
```

### 2. Create Network & Setup Environment

```bash
docker network create wordpress-network
cp .env.example .env
```

Edit `.env` with your passwords:
```env
MYSQL_ROOT_PASSWORD=secure_root_password
MYSQL_PASSWORD=secure_user_password
PHP_VERSION=8.2
```

### 3. Start Stack

```bash
docker compose up -d
```

### 4. Access WordPress

- **WordPress Admin**: `http://localhost:8080/wp-admin`
- **Varnish Purge Plugin**: Admin → Tools → Varnish Purge
- **Database**: Accessible on `localhost:3306`

## Using the Varnish Purge Plugin

The WordPress admin panel includes a dedicated **Varnish Purge Plugin** for easy cache management:

### Access the Plugin

1. Log in to WordPress admin: `http://localhost:8080/wp-admin`
2. Navigate to: **Tools → Varnish Purge**

### Features

- **Connection Status**: Shows ✓ Connected when Varnish is reachable
- **Purge All Cache**: One-click button to clear entire cache
- **Purge Specific URL**: Enter a page URL to purge just that cache entry
- **Auto-Purge**: Automatically purges affected cache when you:
  - Publish or update a post/page
  - Delete a post
  - Move a post to trash

### Manual CLI Purge Commands

For administrators with terminal access:

```bash
# Purge entire cache
docker exec -it wordpress curl -X PURGE -H "X-Purge-All: true" http://varnish:6081/

# Purge specific URL
docker exec -it wordpress curl -X PURGE http://varnish:6081/your-page-path/

# Purge from external server
curl -X PURGE http://your-domain.com:6081/

# Check Varnish logs for purge activity
docker logs varnish | grep -i purge
```

---

## Benefits of This Configuration

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

## PHP Configuration

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

---

## Configuration Files Reference

### Apache Process Limiting (`php/apache-mpm.conf`)

Limits Apache to 4 concurrent worker processes to prevent server overload:

```apache
StartServers               2
MaxSpareServers            3
MaxRequestWorkers          4
MaxConnectionsPerChild     0
```

**Purpose**: On shared hosting or small instances, prevents runaway processes from consuming all system memory. Adjust `MaxRequestWorkers` based on your server capacity.

Restart to apply changes:
```bash
docker compose restart wordpress
```

### Varnish Cache Rules (`varnish/default.vcl`)

Key configuration details:

| Setting | Value | Purpose |
|---------|-------|---------|
| Cache TTL (default) | 15 minutes | How long to cache normal pages |
| Cache TTL (static) | 24 hours | How long to cache CSS, JS, images |
| Grace period | 30 minutes | Serve stale content if backend down |
| PURGE allowed from | Private networks | Prevents external cache clearing |

**Excluded from cache** (always fresh):
- `wp-admin`, `wp-login` (admin pages)
- `/cart`, `/checkout`, `/my-account` (ecommerce)
- Any URL with query parameters
- Logged-in users (cookie: `wordpress_logged_in_*`)

---

## Docker Compose Commands

```bash
# Start all services
docker compose up -d

# Stop all services
docker compose down

# View service logs
docker logs wordpress          # WordPress/Apache logs
docker logs varnish          # Varnish cache logs
docker logs mariadb          # Database logs

# Restart a service
docker compose restart wordpress
docker compose restart varnish

# Open WordPress shell
docker exec -it wordpress bash

# Check WordPress plugin directory
docker exec -it wordpress ls -la /var/www/html/wp-content/plugins/

# Clear Docker system (cleanup unused images/volumes)
docker system prune -a
```

---

## Troubleshooting

### Cache Not Purging After Content Update

**Symptom**: Updated post still shows old content on website

**Solutions**:
1. Check plugin is activated: Tools → Plugins, search "Varnish Purge"
2. Verify Varnish connection: Tools → Varnish Purge, check for ✓ Connected status
3. Test manual purge: Use the "Purge All Cache" button
4. Check logs: `docker logs varnish | grep -i purge`

### WordPress Won't Connect to Varnish

**Symptom**: Varnish Purge Plugin shows "Connection failed"

**Solutions**:
1. Verify Varnish container is running: `docker ps | grep varnish`
2. Check networks: `docker network inspect wordpress-network`
3. Restart Varnish: `docker compose restart varnish`
4. Check firewall: Port 6081 must be open between containers

### Site Shows Cached Content for Logged-in Users

**Symptom**: Admin sees old content even after publishing new content

**Solutions**:
1. Clear browser cache (Ctrl+Shift+Delete)
2. Check WordPress login session: Logout and login again
3. Check cookie: Browser dev tools → Application → Cookies, look for `wordpress_logged_in_`
4. Verify Varnish rules: `docker exec varnish varnishadm vcl.list`

### PHP Memory Limit Exceeded

**Symptom**: Error "Allowed memory size exhausted"

**Solution**: Edit `php/custom.ini`, update `memory_limit = 512M`, restart:
```bash
docker compose restart wordpress
```

---

## Optional: Caddy Edge Proxy (HTTPS/TLS)

For production deployments requiring HTTPS with automatic certificate management:

1. In the `caddy/` folder, create `.env`:
   ```bash
   cd caddy && cp .env.example .env
   ```

2. Update `caddy/.env` with your domain:
   ```env
   DOMAIN=example.com
   EMAIL=admin@example.com
   ```

3. Start Caddy:
   ```bash
   docker compose up -d
   ```

Caddy will automatically:
- Obtain SSL certificates from Let's Encrypt
- Reverse proxy traffic to WordPress/Varnish
- Renew certificates before expiration
- Enforce HTTPS redirects

See [Caddy Configuration](caddy/README.md) for advanced options.

---

## Variable Expansion in docker-compose.yml

The `docker compose` command automatically loads variables from `.env` file and applies them to `docker-compose.yml`. This allows:
- Database password configuration
- Memory allocation for containers
- Port mappings
- Service profiling (enable/disable services)

Edit `.env` and restart services for changes to take effect:
```bash
docker compose up -d --force-recreate
```

---

## Architecture Overview

```
┌─────────────────────────────────────┐
│  Browser / External Client          │
└──────────────┬──────────────────────┘
               │ HTTP/HTTPS (port 80/443)
┌──────────────▼──────────────────────┐
│  Caddy (Optional)                   │ ← HTTPS termination, reverse proxy
│  :80 / :443                         │
└──────────────┬──────────────────────┘
               │ (WordPress network)
┌──────────────▼──────────────────────┐
│  Varnish Cache Layer                │ ← Caches pages, static assets
│  :6081                              │   Auto-purge on content updates
└──────────────┬──────────────────────┘
               │
┌──────────────▼──────────────────────┐
│  WordPress (Apache + PHP 8.2)       │ ← Processes requests, manages content
│  :8080                              │   Auto-purges cache on post updates
└──────────────┬──────────────────────┘
               │
┌──────────────▼──────────────────────┐
│  MariaDB Database                   │ ← Stores all WordPress data
│  :3306                              │
└─────────────────────────────────────┘
```

---

## Environment Variable Reference

Key variables in `.env`:

| Variable | Default | Purpose |
|----------|---------|---------|
| MYSQL_DATABASE | wordpress | Database name |
| MYSQL_USER | wordpress | DB user |
| MYSQL_PASSWORD | (required) | DB password |
| MYSQL_ROOT_PASSWORD | (required) | Root password |
| WORDPRESS_DB_HOST | mariadb | Database hostname |
| WORDPRESS_TABLE_PREFIX | wp_ | Table prefix |
| VARNISH_ENABLED | true | Enable/disable Varnish |
| VARNISH_MEMORY | 512m | Cache memory allocation |
| VARNISH_PORT | 6081 | Varnish listen port |
| VARNISH_CACHE_TTL | 15m | Default cache duration |
| VARNISH_EXCLUDE_PAGES | wp-admin<br/>wp-login<br/>/cart<br/>/checkout<br/>/my-account | URLs never cached |

---

## Security Considerations

- **Disable Varnish in development**: Set `VARNISH_ENABLED=false` in `.env`
- **Use strong passwords**: Generate 32+ character passwords for MySQL
- **Enable HTTPS**: Use Caddy or your own SSL certificates
- **Limit admin access**: Use firewall rules to restrict wp-admin to known IPs
- **Regular backups**: Backup `db_data` and `wp_data` volumes regularly
- **Keep updated**: Update WordPress plugins and themes regularly

---

## Support & Additional Resources

- **Varnish Documentation**: https://varnish-cache.org/docs/
- **WordPress Codex**: https://developer.wordpress.org/
- **MariaDB Documentation**: https://mariadb.com/docs/
- **Docker Compose Reference**: https://docs.docker.com/compose/
