# Caddy terminates TLS in front of the application nginx

The application nginx listened on port 80 and the stack had no TLS at all:
there was no certificate, no `server_name`, and port 443 was closed. Telegram
webhooks require a public HTTPS URL with a valid certificate, so the domain
work is a prerequisite for the bot rather than a cosmetic improvement.

We added a `caddy:2-alpine` service to the production compose override. Caddy
binds ports 80 and 443, obtains and renews a Let's Encrypt certificate for
`APP_DOMAIN` automatically, and proxies to `webserver:80` over the private
Docker network. The production override resets the application nginx port
binding, so the app server cannot be reached directly from outside; in local
development the base compose file still exposes it on loopback
(`127.0.0.1:${NGINX_PORT}:80`).
The application trusts private-range proxies, which lets Symfony read
`X-Forwarded-Proto` and mark sessions with `cookie_secure`.

## Considered Options

- **Certbot with the existing nginx** — requires a renewal timer or cron, a
  webroot/challenge configuration, and an nginx reload hook; more moving parts
  for the same result.
- **Cloudflare proxy with origin certificates** — moves TLS and DNS to an
  external provider and hides the origin; adds a dependency and a second place
  to debug when HTTPS breaks.
- **TLS directly in the application nginx** — couples certificate lifecycle to
  the app image and exposes port 443 from the app container; the web server
  would need to be reconfigured on every certificate change.
- **Replacing nginx with Caddy entirely** — would simplify the stack, but
  rewrites a working configuration and is unrelated to the feature.

## Consequences

- Changing the domain means updating `APP_DOMAIN` and restarting the stack.
- Ports 80 and 443 must stay open; HTTP-01 validation needs port 80.
- Certificate state lives in the `caddy-data` and `caddy-config` volumes, which
  must survive `make stop` (the stack uses `down`, not `down -v`).
- Local development does not run Caddy: the app is still reachable at
  `http://localhost:<NGINX_PORT>`.
