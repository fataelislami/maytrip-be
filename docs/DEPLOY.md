# Maytrip Backend — Deployment Guide

End-to-end instructions for shipping the backend to a fresh Ubuntu VPS
(Singapore, 2 GB / 2 CPU) using Docker Compose.

> **Stack**: Caddy (auto-HTTPS) → PHP-FPM 8.3 → MySQL 8.4. Everything runs
> as containers. Uploads live in a named Docker volume.

---

## 0. Prerequisites

- A VPS running Ubuntu 22.04 or 24.04 LTS (Tencent Cloud Singapore in our case).
- Domain `maytrip.co` with DNS managed by Cloudflare.
- Cloudflare API access (for SSL via DNS challenge — optional, HTTP-01 works too).
- GitHub access to clone `maytrip-be` (deploy key recommended).

## 1. DNS setup (do this first)

In the Cloudflare dashboard for `maytrip.co`:

| Type | Name | Content        | Proxy |
| ---- | ---- | -------------- | ----- |
| A    | api  | `<VPS_IP_v4>`  | DNS only (grey cloud — Caddy needs to see real client IP and handle TLS itself) |
| A    | @    | `<VPS_IP_v4>`  | (optional, only if you host the marketing site on the VPS too — for Cloudflare Pages, point to the Pages target instead) |

Wait ~2 minutes for propagation. Verify:
```bash
dig +short api.maytrip.co
```

## 2. Initial VPS hardening

SSH in as root (one-time):

```bash
ssh root@<VPS_IP>
```

```bash
# Update
apt update && apt upgrade -y

# Create a non-root user with sudo
adduser maytrip
usermod -aG sudo maytrip

# Copy your SSH key to the new user
mkdir -p /home/maytrip/.ssh
cp ~/.ssh/authorized_keys /home/maytrip/.ssh/
chown -R maytrip:maytrip /home/maytrip/.ssh
chmod 700 /home/maytrip/.ssh
chmod 600 /home/maytrip/.ssh/authorized_keys

# Disable root SSH + password auth
sed -i 's/^#\?PermitRootLogin.*/PermitRootLogin no/' /etc/ssh/sshd_config
sed -i 's/^#\?PasswordAuthentication.*/PasswordAuthentication no/' /etc/ssh/sshd_config
systemctl restart ssh
```

Reconnect as the new user from a new terminal — **don't close the root session
until you've confirmed `maytrip` works**:

```bash
ssh maytrip@<VPS_IP>
```

## 3. Firewall (UFW)

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 443/udp   # Caddy HTTP/3
sudo ufw enable
sudo ufw status
```

## 4. Swap (2 GB) — insurance for tight RAM

```bash
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
sudo sysctl vm.swappiness=10
echo 'vm.swappiness=10' | sudo tee -a /etc/sysctl.conf
free -h   # confirm Swap line shows 2.0 G
```

## 5. Install Docker + Compose

```bash
sudo apt install -y ca-certificates curl gnupg
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
sudo chmod a+r /etc/apt/keyrings/docker.gpg
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
  https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" \
  | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# Run docker without sudo
sudo usermod -aG docker $USER
newgrp docker     # apply immediately for this shell

docker run --rm hello-world   # sanity check
```

## 6. Clone the repo

```bash
sudo mkdir -p /opt/maytrip-be
sudo chown $USER:$USER /opt/maytrip-be
git clone https://github.com/fataelislami/maytrip-be.git /opt/maytrip-be
cd /opt/maytrip-be
```

(Or use a deploy key if you prefer SSH — `git@github.com:fataelislami/maytrip-be.git`.)

## 7. Configure environment

```bash
cp .env.production.example .env.production
chmod 600 .env.production

# Generate strong passwords (one each for DB_PASSWORD and DB_ROOT_PASSWORD)
openssl rand -hex 24
openssl rand -hex 24

# Get your UID/GID
id   # note uid=<N>, gid=<N>
```

Edit `.env.production` and fill in:
- `DB_PASSWORD`, `DB_ROOT_PASSWORD` (the two random strings)
- `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` (create NEW prod credentials at
  https://console.cloud.google.com/apis/credentials — see "Google OAuth" below)
- `WWWUSER`, `WWWGROUP` (your uid/gid from `id`)

Leave `APP_KEY` empty — `deploy/init.sh` will generate one.

### Google OAuth — production credentials

In Google Cloud Console → APIs & Services → Credentials:
1. **Create OAuth 2.0 Client ID** (Web application).
2. Authorised JavaScript origins: `https://maytrip.co`, `https://api.maytrip.co`
3. Authorised redirect URIs: `https://api.maytrip.co/api/auth/google/callback`
4. Copy Client ID + Client Secret into `.env.production`.

> Do **not** reuse the development credentials — production callback URL is
> different and dev secrets shouldn't have access to real user accounts.

## 8. First-time deploy

```bash
cd /opt/maytrip-be
bash deploy/init.sh
```

The script will:
- Build the PHP image
- Start Caddy, PHP-FPM, MySQL
- Wait for MySQL health check
- `composer install --no-dev --optimize-autoloader`
- Generate `APP_KEY`
- Run `php artisan migrate --force`
- Cache config / routes / views
- Fix storage perms

Watch the output. First boot can take ~3 minutes (Caddy needs to fetch a
Let's Encrypt cert).

Verify:
```bash
curl -I https://api.maytrip.co
# Expected: HTTP/2 200 (or a 404 from Laravel — that's fine, the TLS is what matters)

curl https://api.maytrip.co/api/trips
# Expected: empty array or JSON list
```

## 9. Daily backups (cron)

```bash
sudo crontab -e
# Add:
0 3 * * *  cd /opt/maytrip-be && bash deploy/backup-db.sh >> /var/log/maytrip-backup.log 2>&1
```

Backups land in `/opt/maytrip-be/backups/maytrip-YYYYMMDD-HHMMSS.sql.gz`,
last 7 kept. Set `BACKUP_R2_BUCKET=r2:maytrip-backups` in `.env.production`
plus `rclone config` if you want off-VPS backups.

## 10. Subsequent deploys (code changes)

```bash
ssh maytrip@<VPS_IP>
cd /opt/maytrip-be
git pull
bash deploy/update.sh
```

Use `--build` flag manually if you change the Dockerfile or PHP extensions:
```bash
docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build
bash deploy/update.sh
```

## 11. Operations cheatsheet

```bash
# Logs (all services)
docker compose --env-file .env.production -f docker-compose.prod.yml logs -f

# Logs (one service)
docker compose --env-file .env.production -f docker-compose.prod.yml logs -f php

# Shell into php container
docker compose --env-file .env.production -f docker-compose.prod.yml exec php sh

# Run artisan command
docker compose --env-file .env.production -f docker-compose.prod.yml exec php php artisan tinker

# Restart one service
docker compose --env-file .env.production -f docker-compose.prod.yml restart caddy

# Stop everything
docker compose --env-file .env.production -f docker-compose.prod.yml down

# Stop + remove volumes (DANGER — wipes DB and uploads)
docker compose --env-file .env.production -f docker-compose.prod.yml down -v

# Disk usage
docker system df
du -sh /var/lib/docker
```

## 12. When to scale up

You'll know it's time to upgrade the VPS when:
- `htop` shows RAM consistently > 80% used
- MySQL slow query log starts populating
- Page load > 1 s on Indonesia origins
- `docker stats` shows php container CPU > 60% sustained

Migrate path:
1. Snapshot the VPS (provider feature)
2. Resize to 4 GB / 2 vCPU
3. `docker compose restart` to use the new RAM
4. Bump `innodb_buffer_pool_size` to 512 M in `docker/mysql/maytrip.cnf`
