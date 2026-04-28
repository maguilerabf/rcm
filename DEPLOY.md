# DEPLOY — Ubuntu 22.04 nativo (sin Docker)

Guía paso a paso para desplegar RCM en un Droplet de DigitalOcean (1-2 GB RAM)
con Ubuntu 22.04, sin contenedores, usando Caddy como reverse proxy con SSL automático.

> Diseñado para `rcm.iaflow.cl`. Reemplaza los valores entre `<…>` por los reales.

---

## 0. Pre-requisitos

- DNS apuntando: `rcm.iaflow.cl A <IP_DEL_DROPLET>` (ya configurado).
- Acceso `root` o un usuario con sudo.
- App Password de Gmail con 2FA activo (mismo del setup local).

---

## 1. Hardening básico

```bash
apt update && apt upgrade -y
apt install -y ufw fail2ban
ufw allow OpenSSH
ufw allow 80,443/tcp
ufw --force enable
systemctl enable --now fail2ban
```

---

## 2. Instalar PHP 8.3, PostgreSQL 16, Node 20, Python, Caddy

```bash
# Repos extra
apt install -y ca-certificates curl gnupg lsb-release software-properties-common debian-archive-keyring

# PHP 8.3 (PPA ondrej)
add-apt-repository -y ppa:ondrej/php
apt update

# PostgreSQL 16 (repo oficial)
sh -c 'echo "deb https://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list'
curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --dearmor -o /etc/apt/trusted.gpg.d/postgresql.gpg
apt update

# Node 20 (NodeSource)
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -

# Caddy (repo oficial)
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | tee /etc/apt/sources.list.d/caddy-stable.list
apt update

# Instalar todo
apt install -y \
    php8.3-fpm php8.3-cli php8.3-pgsql php8.3-mbstring php8.3-xml \
    php8.3-zip php8.3-intl php8.3-bcmath php8.3-curl php8.3-opcache \
    postgresql-16 postgresql-contrib-16 \
    nodejs \
    python3 python3-pip python3-openpyxl \
    caddy git unzip
```

Verificar:
```bash
php -v        # 8.3.x
psql --version  # 16.x
node -v       # v20.x
python3 -c "import openpyxl; print(openpyxl.__version__)"
caddy version
```

Composer (manual):
```bash
EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"
if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]; then echo BADHASH; rm composer-setup.php; exit 1; fi
php composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php
composer --version
```

---

## 3. PostgreSQL: usuario, BD, extensiones

```bash
sudo -u postgres psql <<SQL
CREATE USER rcm WITH PASSWORD '<DB_PASSWORD>';
CREATE DATABASE rcm OWNER rcm;
\c rcm
CREATE EXTENSION IF NOT EXISTS unaccent;
GRANT ALL ON SCHEMA public TO rcm;
SQL

# Si la app necesita conectarse, asegurarse que pg_hba.conf permita auth password local (default OK).
systemctl enable --now postgresql
```

Test:
```bash
PGPASSWORD='<DB_PASSWORD>' psql -h localhost -U rcm -d rcm -c "SELECT version();"
```

---

## 4. Crear usuario sistema y clonar el repo

```bash
adduser --system --group --home /var/www/rcm --shell /bin/bash rcm
sudo -u rcm git clone https://github.com/maguilera89/rcm.git /var/www/rcm/app
cd /var/www/rcm/app
```

---

## 5. Configurar `.env`

```bash
sudo -u rcm cp .env.production.example /var/www/rcm/app/app/.env.local
sudo -u rcm nano /var/www/rcm/app/app/.env.local
```

Llenar:
```
APP_ENV=prod
APP_SECRET=<openssl rand -hex 32>
DATABASE_URL="postgresql://rcm:<DB_PASSWORD>@127.0.0.1:5432/rcm?serverVersion=16&charset=utf8"
MAILER_DSN=smtp://m.aguilera89%40gmail.com:<APP_PASSWORD>@smtp.gmail.com:587
MAILER_FROM="RCM <m.aguilera89@gmail.com>"
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
TRUSTED_PROXIES=127.0.0.1,::1
STORAGE_THRESHOLD_BYTES=10737418240
STORAGE_TARGET_BYTES=5368709120
```

---

## 6. Instalar dependencias y compilar assets

```bash
cd /var/www/rcm/app/app
sudo -u rcm composer install --no-dev --optimize-autoloader
sudo -u rcm npm ci
sudo -u rcm npm run build
sudo -u rcm php bin/console cache:clear --env=prod
sudo -u rcm php bin/console cache:warmup --env=prod
sudo -u rcm php bin/console doctrine:migrations:migrate --no-interaction --env=prod
sudo -u rcm mkdir -p var/uploads/{telesalud,inscritos,exports} var/sessions/prod var/log var/cache
sudo -u rcm chmod -R u+rwX var/
```

Crear primer admin:
```bash
sudo -u rcm php bin/console app:user:create m.aguilera89@gmail.com '<PASSWORD>' Mauricio Aguilera --env=prod
```

---

## 7. PHP-FPM: configurar pool dedicado

`/etc/php/8.3/fpm/pool.d/rcm.conf`:
```ini
[rcm]
user = rcm
group = rcm
listen = /run/php/rcm.sock
listen.owner = caddy
listen.group = caddy
listen.mode = 0660
pm = dynamic
pm.max_children = 8
pm.start_servers = 2
pm.min_spare_servers = 2
pm.max_spare_servers = 4
pm.max_requests = 500
php_admin_value[memory_limit] = 256M
php_admin_value[upload_max_filesize] = 100M
php_admin_value[post_max_size] = 100M
php_admin_value[max_execution_time] = 300
php_admin_value[date.timezone] = America/Santiago
catch_workers_output = yes
```

Quitar el pool default si no se usa:
```bash
mv /etc/php/8.3/fpm/pool.d/www.conf /etc/php/8.3/fpm/pool.d/www.conf.disabled
systemctl restart php8.3-fpm
```

Verificar el socket existe:
```bash
ls -la /run/php/rcm.sock
```

---

## 8. Caddy: reverse proxy + SSL automático

`/etc/caddy/Caddyfile`:
```caddy
rcm.iaflow.cl {
    root * /var/www/rcm/app/app/public
    encode gzip zstd

    php_fastcgi unix//run/php/rcm.sock {
        try_files {path} {path}/ index.php
    }

    file_server

    # Cabeceras de seguridad
    header {
        Strict-Transport-Security "max-age=31536000; includeSubDomains"
        X-Content-Type-Options "nosniff"
        X-Frame-Options "SAMEORIGIN"
        Referrer-Policy "strict-origin-when-cross-origin"
        -Server
    }

    log {
        output file /var/log/caddy/rcm-access.log {
            roll_size 10mb
            roll_keep 5
        }
    }
}
```

```bash
mkdir -p /var/log/caddy
caddy validate --config /etc/caddy/Caddyfile
systemctl reload caddy
```

Caddy emite el certificado Let's Encrypt automáticamente la primera vez que recibe tráfico HTTPS.

Test:
```bash
curl -I https://rcm.iaflow.cl
```

---

## 9. Worker de Symfony Messenger como systemd service

`/etc/systemd/system/rcm-worker.service`:
```ini
[Unit]
Description=RCM Symfony Messenger Worker
After=network.target postgresql.service

[Service]
Type=simple
User=rcm
Group=rcm
WorkingDirectory=/var/www/rcm/app/app
Environment="APP_ENV=prod"
ExecStart=/usr/bin/php bin/console messenger:consume async --time-limit=3600 --memory-limit=256M --no-debug
Restart=on-failure
RestartSec=5
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

```bash
systemctl daemon-reload
systemctl enable --now rcm-worker
systemctl status rcm-worker
journalctl -u rcm-worker -f      # ver logs en vivo
```

---

## 10. Cron diario para cleanup (safety net)

`/etc/cron.d/rcm`:
```cron
# Cleanup diario a las 03:00 hora server (UTC; si quieres Chile, ajusta)
0 3 * * * rcm cd /var/www/rcm/app/app && /usr/bin/php bin/console app:cleanup:storage --env=prod >> /var/log/rcm-cleanup.log 2>&1
```

```bash
touch /var/log/rcm-cleanup.log
chown rcm:rcm /var/log/rcm-cleanup.log
```

---

## 11. Backup diario de Postgres

`/etc/cron.d/rcm-backup`:
```cron
30 2 * * * postgres pg_dump -Fc rcm > /var/backups/rcm/rcm-$(date +\%Y\%m\%d).dump && find /var/backups/rcm -mtime +7 -name 'rcm-*.dump' -delete
```

```bash
mkdir -p /var/backups/rcm
chown postgres:postgres /var/backups/rcm
```

---

## 12. Verificación final

```bash
# Caddy + Let's Encrypt
curl -I https://rcm.iaflow.cl

# Login
curl -sk -c /tmp/c.txt -X POST https://rcm.iaflow.cl/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"m.aguilera89@gmail.com","password":"<PASSWORD>"}'

# Worker corriendo
systemctl is-active rcm-worker
```

Abrir en el browser: <https://rcm.iaflow.cl> → login con tu cuenta → subir un xlsx → verificar coincidencias.

---

## Update workflow (siguientes deploys)

```bash
sudo -u rcm bash <<'EOF'
cd /var/www/rcm/app
git pull
cd app
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php bin/console cache:clear --env=prod
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
EOF

systemctl restart php8.3-fpm
systemctl restart rcm-worker
```

---

## Troubleshooting

**Caddy no emite SSL**: verificar `dig rcm.iaflow.cl` resuelve a la IP correcta + UFW abre 80/443.

**502 al hacer login**: revisa `journalctl -u php8.3-fpm` y `tail /var/log/caddy/rcm-access.log`.

**Worker no procesa**: `systemctl status rcm-worker` y `journalctl -u rcm-worker -n 50`.

**Permisos en `var/`**: `chown -R rcm:rcm /var/www/rcm/app/app/var`.

**Migraciones no corren**: verificar `DATABASE_URL` en `.env.local` y que la extensión `unaccent` esté instalada (`\dx unaccent` en psql).
