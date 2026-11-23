# Deploying Sanctum Scan / QRPay on AWS EC2

Single-instance topology: Ubuntu 22.04 or 24.04, Nginx, PHP 8.4.1+ FPM (8.5 is
supported), MySQL 8 (or RDS), local filesystem for uploads. This runbook covers
the steps a generic Laravel guide misses for this application: reference-data
seeding, Passport keys, the scheduler/queue loop, cache ordering, and the asset
symlink.

## 1. Server packages

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y nginx mysql-server unzip git \
  php8.4-cli php8.4-fpm php8.4-mysql php8.4-mbstring php8.4-xml \
  php8.4-curl php8.4-zip php8.4-gd php8.4-bcmath php8.4-intl \
  php8.4-redis php8.4-opcache
# Composer 2 from getcomposer.org, installed to /usr/local/bin/composer
```

`composer.json` requires PHP `^8.4.1`. Verify the runtime can satisfy every
extension after installing dependencies:

```bash
composer check-platform-reqs --no-dev
```

## 2. Application install

```bash
sudo mkdir -p /var/www/sanctum-scan
sudo chown -R ubuntu:ubuntu /var/www/sanctum-scan
git clone <repository> /var/www/sanctum-scan
cd /var/www/sanctum-scan
composer install --no-dev --optimize-autoloader
```

## 3. Environment

```bash
cp .env.example .env
php artisan key:generate
```

Production values that matter for this app:

| Key | Value | Why |
| --- | --- | --- |
| `APP_ENV` | `production` | Enables `URL::forceScheme('https')` and disables debug tooling |
| `APP_DEBUG` | `false` | Never expose stack traces |
| `APP_URL` | `https://your-domain` | Base for `asset()`, storage URLs, mail links, OG metadata |
| `APP_MODE` | `live` | `demo` gates money-moving routes behind `app.mode` |
| `APP_TIMEZONE` | e.g. `Africa/Dar_es_Salaam` | Must match the business day used in reports |
| `FILESYSTEM_DISK` | `public` | Local uploads served through `public/storage` |
| `QUEUE_CONNECTION` | `database` | Requires the `jobs` table (migrated) and a worker |
| `SESSION_DRIVER` | `file` (default) | Switch to `database`/`redis` before running multiple app servers |
| `SESSION_SECURE_COOKIE` | `true` | Send the session cookie only over HTTPS (policy SECURITY.md §7.1) |
| `SESSION_DOMAIN` | your domain | Keep the cookie scoped to the deployed host; leave unset for host-only cookies |
| `DB_*` | MySQL 8 / RDS values | Charset `utf8mb4`, collation `utf8mb4_unicode_ci` |
| `SECRET_HASH` | provider webhook secret | Virtual-card webhook signature (`config/virtual_card.php`) |

Do not put secrets in `.env.example`; keep the real `.env` out of version
control.

## 4. Database: migrate, then seed once

```bash
php artisan migrate --force
php artisan db:seed --force
```

Seeding is **not optional**. The seeders create the singleton settings rows
(basic settings, payment gateways, currencies, languages) that most pages
dereference; an empty database produces 500s on `/`. Since the seeders use
fixed primary keys, `DatabaseSeeder` refuses to run twice and exits with a skip
message. Rebuild only when you intend to lose data:

```bash
php artisan migrate:fresh --seed --force
```

## 5. Passport keys and personal access client

The API guards (`auth:api`, `auth:merchant_api`, `auth:agent_api`) use Passport,
and mobile/merchant logins call `createToken()->accessToken`. `storage/*.key`
is gitignored, so a fresh server has no keys and token issuance fails.

```bash
php artisan passport:install
```

Run this **once per environment**. It generates `storage/oauth-private.key` and
`storage/oauth-public.key` and creates the personal-access client. If keys
already exist and only need regenerating:

```bash
php artisan passport:keys --force
```

Keys live outside version control; include `storage/oauth-*.key` in your
secrets backup.

## 6. Asset symlink

```bash
mkdir -p storage/app/public
php artisan storage:link
```

Everything rendered through `get_image()` / `get_logo()` resolves to
`/storage/...`; without the symlink every uploaded image 404s. Re-run after
moving the application directory (`--force` recreates the link).

## 7. Caches, in this order

```bash
php artisan optimize:clear   # clears config, route, view, event caches
php artisan optimize         # caches config, events, routes, views
```

Notes:

- Run `optimize:clear` **before** re-caching after any `.env` or `config/*.php`
  edit. `optimize` boots the app first; a stale `bootstrap/cache/config.php`
  would otherwise be re-serialized.
- `php artisan route:cache` works as of 2026-11-18 (R-26 duplicate route names
  resolved). Do not introduce duplicate route names — `RouteRegistryTest` fails
  and route caching breaks.
- Never call `env()` outside `config/`; with cached config it returns `null`.

## 8. Permissions

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;
```

Application code stays owned by the deploy user and read-only to PHP.

## 9. Nginx

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/sanctum-scan/public;
    index index.php;

    charset utf-8;
    client_max_body_size 32m;   # KYC documents and payment evidence uploads

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ /\.(?!well-known).* { deny all; }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param HTTPS $https if_not_empty;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|svg|webp|woff2?)$ {
        expires 30d;
        access_log off;
        try_files $uri /index.php?$query_string;
    }
}
```

Then `sudo nginx -t && sudo systemctl reload nginx` and issue TLS with
`sudo certbot --nginx -d your-domain.com`.

## 10. Scheduler

The scheduler drains the queue, retries failed jobs, and refreshes currency
rates:

```
* * * * * cd /var/www/sanctum-scan && php artisan schedule:run >> /dev/null 2>&1
```

Install it in the deploy user's crontab (not root's) and confirm with
`php artisan schedule:list`.

## 11. Queue worker

`QUEUE_CONNECTION=database` plus the five-minute `queue:work
--stop-when-empty` schedule means jobs are processed without a daemon, but a
long-running worker lowers latency. Optional supervisor unit:

```ini
[program:sanctum-scan-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/sanctum-scan/artisan queue:work --sleep=3 --tries=3 --max-time=3600
directory=/var/www/sanctum-scan
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/sanctum-scan/storage/logs/worker.log
stopwaitsecs=3600
```

Restart workers on every deploy so they pick up new code:

```bash
php artisan queue:restart
```

## 12. Verification checklist

```bash
curl -I https://your-domain.com/            # 200, no stack trace
curl -I https://your-domain.com/storage/... # 200 for a known uploaded image
php artisan about                           # environment=production, debug=false
php artisan schedule:list                   # three scheduled entries
php artisan queue:work --stop-when-empty    # exits cleanly
```

Then exercise a login through `/api/v1/...` (Passport token), open a public
scan page at `/scan/{reference}`, and load the admin dashboard.

## 13. Update deploy

```bash
cd /var/www/sanctum-scan
git pull --ff-only
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
php artisan queue:restart
sudo systemctl reload php8.3-fpm
```

Do not re-run `db:seed` or `passport:install` on updates; both are install-time
steps. Take a database and `storage/app/public` backup before migrations that
alter existing tables.

## 14. Rollback

1. Put the app in maintenance mode: `php artisan down --retry=60`.
2. Check out the previous release tag/commit.
3. `composer install --no-dev --optimize-autoloader`.
4. Roll the database back with `php artisan migrate:rollback --step=1
   --force` **only when the migration is reversible**, otherwise restore the
   pre-deploy dump.
5. `php artisan optimize:clear && php artisan optimize`, `queue:restart`.
6. `php artisan up`.

## 15. Troubleshooting

| Symptom | Cause | Fix |
| --- | --- | --- |
| 500 on `/`, logs show property access on null | Database migrated but not seeded | `php artisan db:seed --force` |
| All `/storage/*` images 404 | Missing symlink | `php artisan storage:link --force` |
| Images work locally, point at the wrong host in production | `APP_URL` mismatch | Set `APP_URL=https://...`, then `optimize:clear && optimize` |
| Config edits ignored after deploy | Stale `bootstrap/cache/config.php` | `php artisan optimize:clear` before re-caching |
| `Unable to prepare route ... for serialization` | Duplicate route name | Make names unique; `RouteRegistryTest` catches it |
| API login 500 (`Personal access client not found`) | Passport not installed | `php artisan passport:install` |
| Uploads fail at ~1 MB | Nginx default body limit | `client_max_body_size 32m` |
| Queue never drains | `schedule:run` cron missing | Add the cron entry from section 10 |

## Topology notes

- Behind an AWS ALB or CloudFront, set `TrustProxies::$proxies` to the load
  balancer ranges (or `'*'` when the origin is unreachable directly) so scheme
  and client IP are read from `X-Forwarded-*`.
- Multiple app servers require shared session/cache drivers (`database` or
  `redis`) and shared object storage; the public disk URL is `APP_URL/storage`,
  so S3 requires a different filesystem disk.
- Schedule `mysqldump` (or RDS snapshots) and back up `storage/app/public` and
  `storage/oauth-*.key` on the same cadence.
