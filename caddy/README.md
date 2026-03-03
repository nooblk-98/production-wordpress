# Standalone Caddy Edge Proxy

This folder contains a separate Docker Compose stack for Caddy with:

- Official Caddy Docker image (`caddy:2.9`)
- Security headers
- Request body size limit
- Reverse proxy to your existing WordPress/Varnish stack

## Quick start

1. Copy env file:

   - PowerShell: `Copy-Item .env.example .env`
   - Bash: `cp .env.example .env`

2. Start Caddy stack:

   - `docker compose up -d --build`

3. Open:

   - HTTP: `http://localhost:8080`
   - HTTPS: `https://localhost:8443`

## Important for your current setup

- Your main stack currently already binds host port `80` with Varnish.
- This Caddy stack defaults to `8080/8443` to avoid port conflicts.

## Security recommendations

1. Keep XML-RPC blocked unless explicitly required.
2. Use strong WordPress admin passwords + MFA plugin.
3. Keep plugin/theme updates automatic where possible.
4. Put CDN/WAF (Cloudflare, etc.) in front for DDoS and bot filtering + rate limiting.
5. Add fail2ban or CrowdSec for repeated auth abuse patterns.
