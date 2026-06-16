# Deploy — SafeChild (GitHub Actions → Zomro)

CI builds on every push to `master`: the runner rsyncs the source to the server,
then over SSH runs `composer install`, `npm run build`, migrations and cache
warmup. Native stack (no Docker in prod): PHP-FPM + Nginx + PostgreSQL + Redis.

Server: `188.137.240.18` · deploy path: `/var/www/savechild`

---

## 1. GitHub repository secrets

Repo → **Settings → Secrets and variables → Actions → New repository secret**:

| Secret | Value |
|---|---|
| `SSH_HOST` | `188.137.240.18` |
| `SSH_PORT` | `22` (or your custom port) |
| `SSH_USER` | `deploy` (the user that owns `/var/www/savechild`) |
| `SSH_PRIVATE_KEY` | **base64** of the deploy key's private key (see below) |

Generate a dedicated deploy key locally and register it on the server:

```bash
ssh-keygen -t ed25519 -f ./savechild_deploy -N ""    # creates savechild_deploy(.pub)
base64 -i ./savechild_deploy | pbcopy                  # → paste into SSH_PRIVATE_KEY secret
# put the PUBLIC key on the server (see step 2: authorized_keys)
```

---

## 2. One-time server provisioning (Ubuntu 22.04/24.04)

Run as root once.

```bash
# --- base + PHP 8.4 ---
apt update
add-apt-repository -y ppa:ondrej/php && apt update
apt install -y nginx postgresql redis-server git unzip \
  php8.4-fpm php8.4-cli php8.4-pgsql php8.4-redis php8.4-mbstring \
  php8.4-xml php8.4-curl php8.4-zip php8.4-bcmath php8.4-intl php8.4-gd

# --- Composer ---
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# --- Node 22 ---
curl -fsSL https://deb.nodesource.com/setup_22.x | bash - && apt install -y nodejs

# --- deploy user + app dir ---
adduser --disabled-password --gecos "" deploy
mkdir -p /var/www/savechild && chown -R deploy:www-data /var/www/savechild

# storage skeleton — CI excludes storage/** from rsync, so create it once:
sudo -u deploy mkdir -p /var/www/savechild/storage/app/public \
  /var/www/savechild/storage/framework/{cache/data,sessions,views} \
  /var/www/savechild/storage/logs \
  /var/www/savechild/bootstrap/cache
# add the deploy key's PUBLIC part:
mkdir -p /home/deploy/.ssh && chmod 700 /home/deploy/.ssh
echo "ssh-ed25519 AAAA...your savechild_deploy.pub..." >> /home/deploy/.ssh/authorized_keys
chmod 600 /home/deploy/.ssh/authorized_keys && chown -R deploy:deploy /home/deploy/.ssh

# --- database + redis ---
sudo -u postgres psql -c "CREATE USER savechild WITH PASSWORD 'STRONGPASS';"
sudo -u postgres psql -c "CREATE DATABASE savechild OWNER savechild;"
```

### `.env` on the server (NOT deployed by CI — create once)

```bash
sudo -u deploy bash -c 'cat > /var/www/savechild/.env' <<'ENV'
APP_NAME=SafeChild
APP_ENV=production
APP_DEBUG=false
APP_URL=http://188.137.240.18

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=savechild
DB_USERNAME=savechild
DB_PASSWORD=STRONGPASS

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
ENV
# first run only — generate the key:
cd /var/www/savechild && sudo -u deploy php artisan key:generate
```

### Nginx vhost — `/etc/nginx/sites-available/savechild`

```nginx
server {
    listen 80;
    server_name 188.137.240.18;
    root /var/www/savechild/public;
    index index.php;

    location / { try_files $uri $uri/ /index.php?$query_string; }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    location ~ /\.(?!well-known).* { deny all; }
}
```

```bash
ln -s /etc/nginx/sites-available/savechild /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx
```

### Queue worker (systemd)

`/etc/systemd/system/savechild-queue.service`:

```ini
[Unit]
Description=SafeChild queue worker
After=network.target redis-server.service

[Service]
User=deploy
WorkingDirectory=/var/www/savechild
ExecStart=/usr/bin/php artisan queue:work redis --sleep=1 --tries=3 --max-time=3600
Restart=always

[Install]
WantedBy=multi-user.target
```

```bash
systemctl enable --now savechild-queue
```
> `php artisan queue:restart` in the deploy step tells the worker to reload code.

---

## 3. Deploy

1. Add the 4 secrets (step 1).
2. Provision the server once (step 2).
3. Push to `master` — or run the workflow manually (Actions → **Deploy PROD** → Run workflow).

```bash
git add -A && git commit -m "Deploy SafeChild" && git push origin master
```

Open **http://188.137.240.18**.

### TLS (optional, if you have a domain)
```bash
apt install -y certbot python3-certbot-nginx
certbot --nginx -d your-domain
```
