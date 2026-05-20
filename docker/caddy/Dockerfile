# Custom Caddy build with the Cloudflare DNS module so Let's Encrypt can use
# DNS-01 challenges (needed when Cloudflare proxy is enabled — port 80 from
# the public internet is terminated at Cloudflare, not at this origin).

FROM caddy:2-builder-alpine AS builder
RUN xcaddy build \
    --with github.com/caddy-dns/cloudflare \
    --with github.com/WeidiDeng/caddy-cloudflare-ip

FROM caddy:2-alpine
COPY --from=builder /usr/bin/caddy /usr/bin/caddy
