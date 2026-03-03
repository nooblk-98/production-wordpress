# Varnish Cache Configuration

## Cache TTL
- **Default TTL**: 15 minutes
- **Grace Period**: 30 minutes (serves stale content if backend is slow)
- **Error Cache**: 1 minute

## Pages & Content CACHED ✅
- Public blog posts
- Public pages
- Product listings
- Category/archive pages
- Search results (for anonymous users)
- Any public content without authentication

## Pages & Content NOT CACHED ❌
- `wp-admin` - WordPress admin panel
- `wp-login` - Login page
- `preview=true` - Preview mode
- `xmlrpc.php` - XML-RPC API
- `/cart` - WooCommerce cart
- `/checkout` - WooCommerce checkout
- `/my-account` - User account pages
- `wc-api` - WooCommerce API

## User Sessions NOT CACHED ❌
- Logged-in users (any WordPress session cookie)
- Requests with Authorization headers
- Users with cookies:
  - `wordpress_logged_in_`
  - `comment_author`
  - `woocommerce_items_in_cart`
  - `woocommerce_cart_hash`

## Request Methods
- **Cached**: GET, HEAD
- **Not Cached**: POST, PUT, DELETE, and other methods

## Cache Purge
Cache can be purged from:
- `localhost` (127.0.0.1)
- IPv6 localhost (::1)
