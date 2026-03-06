# Varnish Cache Configuration

**Part of:** [Production WordPress Stack](../README.md) | [Caddy Edge Proxy](../caddy/README.md)

## Quick Start

1. **Enable Varnish** in `.env`:
   ```env
   VARNISH_ENABLED=true
   VARNISH_MEMORY=512m
   VARNISH_PORT=6081
   ```

2. **Start stack**:
   ```bash
   docker compose up -d
   ```

3. **Check cache is working**:
   ```bash
   # Should see X-Varnish header in response
   curl -i http://localhost:8080/
   ```

---

## Cache Purge Methods

### Method 1: WordPress Admin Plugin (Easiest)

**Location:** WordPress Admin → Tools → Varnish Purge

Features:
- ✅ One-click "Purge All Cache" button
- ✅ Purge specific page by URL
- ✅ See connection status (✓ Connected / ✗ Failed)
- ✅ **Automatic**: Purges affected cache when post/page published or updated

### Method 2: Docker Command

Purge from terminal with `docker exec`:

```bash
# Purge all cache
docker exec -it wordpress curl -X PURGE -H "X-Purge-All: true" http://varnish:6081/

# Purge specific URL
docker exec -it wordpress curl -X PURGE http://varnish:6081/blog/my-post/

# View purge results
docker logs varnish | grep "PURGE"
```

### Method 3: External HTTP Request

From any server with network access:

```bash
# Purge from external server
curl -X PURGE http://your-domain.com:6081/

# Purge with header
curl -X PURGE -H "X-Purge-All: true" http://your-domain.com:6081/
```

**Note:** Only requests from private IP ranges (Docker network, 127.0.0.1) are allowed. External public IPs will be rejected for security.

---

## Cache Configuration

### Cache TTL (Time To Live)

| Content Type | Duration | Purpose |
|---|---|---|
| **Default pages** | 15 minutes | HTML content |
| **Static assets** | 24 hours | CSS, JavaScript, images, fonts |
| **Error responses** | 1 minute | Failed/temporary errors |
| **4xx responses** | Not cached | Prevents stale 404 pages |

### Grace Period

- **30 minutes** - If WordPress backend is down, serves cached content instead of error

### Request Methods

- **Cached** ✅: GET, HEAD
- **Not Cached** ❌: POST, PUT, DELETE (dynamic requests)

---

## What Gets Cached ✅

- Public blog posts & pages
- Product listings (WooCommerce)
- Category & archive pages
- Search results (anonymous users)
- Static assets: images, CSS, JavaScript, fonts, video
- Any public content without authentication

---

## What NEVER Gets Cached ❌

### Admin & Authentication Pages
- `wp-admin/*` - WordPress administration panel
- `wp-login.php` - User login page
- `preview=true` - Preview mode

### WooCommerce Pages
- `/cart/` - Shopping cart
- `/checkout/` - Checkout process
- `/my-account/` - User account dashboard

### Dynamic Content
- `xmlrpc.php` - XML-RPC API
- Requests with `__SID` or `noCache` parameter
- Requests with Authorization header

### Logged-in Users

Any visitor with these cookies **bypasses cache** to ensure personalized content:
- `wordpress_logged_in_*` - WordPress session
- `wordpress_sec_*` - Security token
- `wp-settings-*` - User preferences
- `wordpress_test_cookie` - Cookie test
- `comment_author` - Comment author
- `woocommerce_*` - Cart/checkout data

**Exception:** Static assets (CSS, JS, images) are cached even with cookies present, then combined with uncached HTML.

---

## Purge Security

Cache can be purged **only** from trusted sources:

### Allowed Sources
- `localhost` (127.0.0.1)
- IPv6 localhost (::1)  
- Docker internal networks (172.16.0.0/12, 10.0.0.0/8, etc.)
- WordPress container (automatic via plugin)

### Rejected Sources
- Public IP addresses (external servers blocked)
- Unknown networks
- PURGE requests without valid source IP

This prevents attackers from clearing cache from the internet.

---

## Monitoring Cache Performance

### Check Cache Hit Rate

```bash
# View Varnish stats (hit vs miss)
docker exec varnish varnishstat -1

# Sample output shows:
# cache_hit     - Requests served from cache
# cache_miss    - Requests that needed WordPress
# cache_hitpass - Requests bypassed cache (like admin pages)
```

### View Request Details

```bash
# Watch live traffic hitting cache
docker exec varnish varnishlog -g request

# Filter for MISS requests (budget killers)
docker exec varnish varnishlog -q 'RespStatus == 200' -g request
```

### Common Patterns

- **High hit rate (>80%)** = Cache working well ✅
- **Low hit rate (<50%)** = Check if cache-busting happens too frequently
- **Lots of PASS** = Logged-in users, check if cookies need adjustment

---

## Varnish Status & Logs

### Service Status

```bash
# Check if Varnish is running
docker ps | grep varnish

# View container logs
docker logs varnish

# Check if listening on port 6081
docker exec varnish netstat -tlnp | grep 6081
```

### Common Log Messages

| Message | Meaning |
|---------|---------|
| `HitPass` | Explicitly bypassing cache (marked in VCL) |
| `HitFull` | Serving from cache ✅ |
| `miss` | Not in cache, fetch from WordPress |
| `PURGE` | Cache cleared successfully |
| `Error 200` | Purge successful |

---

## Configuration File

The Varnish cache rules are defined in `varnish/default.vcl`:

```varnish
# Lines 1-30: ACL (Access Control List)
#   Defines which IPs can purge cache (only private networks)

# Lines 31-80: vcl_recv (Receive hook)
#   Checks PURGE method, applies exclusions, sets cache flags

# Lines 81-150: Cache setting rules
#   Sets TTL based on content type, handles cookies

# Lines 151-174: Error handling
#   Defines error pages and grace behavior
```

To modify cache rules:
1. Edit `varnish/default.vcl`
2. Restart Varnish: `docker compose restart varnish`
3. Verify with: `docker exec varnish varnishlog`

---

## Query Parameters (Bypass)

Requests with these query parameters **always bypass cache**:

| Parameter | Example | Purpose |
|---|---|---|
| `__SID` | `?__SID=abc123` | Session ID parameter |
| `noCache` | `?noCache=1` | Explicit no-cache request |

Add more by editing `varnish/default.vcl`, line ~45:
```varnish
if (req.url ~ "(__SID|noCache|your-parameter)") {
    return (pass);
}
```

---

## Disable Varnish for Development

To work without caching (clearer during development):

```bash
# Edit .env
VARNISH_ENABLED=false

# Restart stack
docker compose down
docker compose up -d
```

Without Varnish, all requests go directly to WordPress (simpler but slower).

---

## Performance Impact

With Varnish enabled on typical WordPress site:

- **Page load time**: 500ms → 50ms (10x faster)
- **Time to first byte**: 200ms → 10ms (20x faster)
- **Concurrent requests**: 50 → 5000+ (100x throughput)
- **Database queries**: Reduced 90%+ on repeat visits
- **Server CPU usage**: 60% → 5% (on cache hits)

---

## Best Practices

1. ✅ **Enable cache expiration** - Set realistic TTLs, don't cache forever
2. ✅ **Test before publishing** - Check featured image loads with cache
3. ✅ **Monitor hit rate** - Use `varnishstat` to verify cache is working
4. ✅ **Update URLs carefully** - Purge cache after permalink changes
5. ✅ **Exclude dynamic content** - Keep cart/checkout fresh with exclusions
6. ✅ **Log cache events** - Review `docker logs varnish` for issues
7. ❌ **Don't over-purge** - Constant purging defeats cache benefits
8. ❌ **Don't cache user-specific content** - Breaks for logged-in visitors

---

For detailed cache configuration changes, see [Production WordPress README](../README.md#varnish-cache-rules-).
