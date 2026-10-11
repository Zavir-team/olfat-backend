# Production deployment guide (classic server)

This app is a Laravel API + static staff UI under `public/`.

> Deploying to **Vercel** instead? Follow `VERCEL_DEPLOY.md`.
> Backup/restore and maintenance procedures: `docs/BACKUP_AND_RECOVERY.md`.
> Add `* * * * * cd /var/www/olfat && php artisan schedule:run >> /dev/null 2>&1` to the web user's crontab
> so daily backups, the weekly integrity check and token pruning run automatically.

## 1. Server requirements

- PHP **8.3+** with extensions: `ctype`, `curl`, `dom`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`
- MySQL 8+ (or MariaDB 10.6+)
- Nginx or Apache
- Composer 2.x
- TLS certificate (HTTPS)

Do **not** use `php artisan serve` in production.

## 2. Deploy steps

```bash
# On the server, in the project root:
cp .env.production.example .env
# Edit .env: APP_KEY, APP_URL, DB_*, SEED_ADMIN_*, mail, session domain

php artisan key:generate
composer install --no-dev --optimize-autoloader

php artisan migrate --force
# Optional first admin (requires SEED_ADMIN_EMAIL + SEED_ADMIN_PASSWORD >= 12 chars):
php artisan db:seed --force

php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Permissions (Linux):
# chown -R www-data:www-data storage bootstrap/cache
# chmod -R ug+rwx storage bootstrap/cache
```

Windows / shared hosting: ensure `storage` and `bootstrap/cache` are writable by the web user.

## 3. Web server document root

Point the vhost document root to the **`public`** directory only.

### Nginx example

```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.com;
    root /var/www/olfat/public;
    index index.php index.html;

    ssl_certificate     /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~* /\.(?!well-known).* {
        deny all;
    }
}
```

### Apache

Ensure `AllowOverride All` for `public/` so `.htaccess` rewrite rules apply. Prefer HTTPS vhost.

## 4. Required production `.env` values

| Key | Value |
|-----|--------|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `LOG_LEVEL` | `error` |
| `APP_URL` | `https://your-domain.com` |
| `DB_CONNECTION` | `mysql` |
| `SESSION_DRIVER` | `database` |
| `CACHE_STORE` | `database` |
| `QUEUE_CONNECTION` | `database` |
| `SEED_ADMIN_*` | strong unique admin credentials |

Copy from `.env.production.example`.

## 5. After deploy checks

1. Open `https://your-domain.com/pages/login.html`
2. Sign in with the production admin
3. Confirm Dashboard loads live API data
4. Create a small sale/purchase and refresh Dashboard / Profit & Loss
5. Confirm HTTP redirects to HTTPS

## 6. Security notes

- Never deploy seeded demo users (`user1@example.com` / `password`)
- Never leave `APP_DEBUG=true` on a public host
- Rotate `APP_KEY` and DB passwords if they were ever shared
- Keep Composer `--no-dev` on production

## 7. Profit & Loss method

Production P&L uses **weighted average** purchase cost for COGS. Home expenses are excluded from business net profit.
