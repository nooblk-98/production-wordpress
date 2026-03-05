# Varnish Cache Configuration

**Part of:** [Production WordPress Stack](../README.md) | [Caddy Edge Proxy](../caddy/README.md)

## Cache TTL
- **Default TTL**: 7 days (604800s)
- **Grace Period**: 30 minutes (serves stale content if backend is slow)
- **Error Cache**: 1 minute
- **4xx Responses**: Not cached (prevents stale missing-asset responses)

## Query Params (Bypass)
- Requests containing `__SID` or `noCache` bypass cache.

## Pages & Content CACHED ✅
- Public blog posts
- Public pages
- Product listings
- Category/archive pages
- Search results (for anonymous users)
- Any public content without authentication

## Pages & Content NOT CACHED ❌
- `wp-admin` - WordPress admin panel
- `wp-login.php` - Login page
- `preview=true` - Preview mode
- `xmlrpc.php` - XML-RPC API
- `/cart/` - WooCommerce cart
- `/checkout/` - WooCommerce checkout
- `/my-account/` - User account pages

## User Sessions NOT CACHED ❌
- Logged-in users (any WordPress session cookie)
- Requests with Authorization headers
- Users with cookies:
  - `wordpress_logged_in_`
  - `wordpress_sec_`
  - `wp-settings-*`
  - `wordpress_test_cookie`
  - `comment_author`
  - `woocommerce_items_in_cart`
  - `woocommerce_cart_hash`

Additional behavior:
- Static assets (`.css`, `.js`, images, fonts, media) are cached even when cookies are present.
- Non-essential analytics cookies are stripped before hashing.
- If any functional/plugin cookie remains, the request bypasses cache to avoid mixed page elements.

## Request Methods
- **Cached**: GET, HEAD
- **Not Cached**: POST, PUT, DELETE, and other methods

## Cache Purge
Cache can be purged from:
- `localhost` (127.0.0.1)
- IPv6 localhost (::1)
