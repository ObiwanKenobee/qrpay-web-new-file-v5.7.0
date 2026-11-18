# Local Development with XAMPP

This guide runs Sanctum Scan locally with XAMPP providing MySQL and Laravel's development server providing HTTP. Apache is not required for the default workflow.

The local launcher binds to `127.0.0.1` by default. It does not create, drop, migrate, or seed databases.

## 1. Requirements

- PHP CLI 8.2 or newer.
- The PHP CLI extensions `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `ctype`, `json`, `tokenizer`, and `xml`.
- Composer 2.
- XAMPP MySQL/MariaDB service.
- Git and curl.

On macOS, the XAMPP PHP executable is commonly located at:

```bash
/Applications/XAMPP/xamppfiles/bin/php
```

Use the same PHP binary for Composer and Artisan. Confirm the CLI version before installing dependencies:

```bash
php -v
php -m
composer --version
```

If the system PHP is not the version you intend to use, add the XAMPP PHP directory to `PATH` before running Composer and Artisan:

```bash
export PATH="/Applications/XAMPP/xamppfiles/bin:$PATH"
php -v
```

## 2. Prepare the project

From the repository root:

```bash
composer install
```

The repository contains `.env.xampp.example` as a local-only configuration template. The launcher copies it to `.env` the first time it is run, then exits so you can configure the database.

Alternatively, copy it manually:

```bash
cp .env.xampp.example .env
```

Edit the database values in `.env`:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sanctum_scan_local
DB_USERNAME=root
DB_PASSWORD=
```

Use the actual MySQL username and password configured in XAMPP. Keep this local `.env` file out of Git; it is already ignored.

## 3. Prepare the local database

Start the MySQL service in XAMPP. In phpMyAdmin, create a database named `sanctum_scan_local` using `utf8mb4_unicode_ci` (or another compatible `utf8mb4` collation).

Choose one of the following paths.

### Option A — Import an existing database backup

This is the preferred route when the goal is to reproduce the current website's content and configuration.

1. Obtain an authorized, sanitized SQL export.
2. Import it into `sanctum_scan_local` using phpMyAdmin.
3. Confirm the export includes the core website tables and the Laravel `migrations` table.
4. Set `.env` to point to this local database.
5. Check the migration state:

```bash
php artisan migrate:status
```

6. Apply only pending migrations:

```bash
php artisan migrate --force
```

Do not run `migrate:fresh` on an imported database. It drops all tables before rebuilding them. Do not run the default seeder over an imported database: several seeders truncate or replace settings and configuration.

Never use a production database export containing real user, merchant, payment, identity, authentication, or other sensitive records for routine local development. Use synthetic or properly sanitized records. Keep local SQL exports in `database/local-dumps/`, which is ignored by Git.

### Option B — Build a fresh disposable local database

Use this only when `sanctum_scan_local` is an empty database created specifically for local development. The application has 207 migration files; a fresh schema can be created and populated with the repository's default seeders:

```bash
php artisan key:generate
php artisan migrate:fresh --seed --force
```

**Destructive warning:** `migrate:fresh` deletes every table in the configured database. Check `DB_DATABASE` before running it, and never point this command at production or a database containing data you need.

The default `DatabaseSeeder` creates a predictable development super-admin account (`superadmin@appdevs.net` with password `appdevs`) and includes seeded provider/configuration values. This is not safe for an exposed or shared environment. Keep the site bound to localhost, do not reuse those credentials elsewhere, and replace the password before any wider testing. The seeders also set `APP_TIMEZONE` to `Asia/Dhaka`; reset it to your intended local timezone after seeding, then clear config:

```bash
php artisan config:clear
```

A fresh database has no production content. The default seeders supply starter settings and website content, not a copy of the current live website.

## 4. Start the website

Run the local preflight and server launcher:

```bash
bash tools/xampp/serve.sh
```

The launcher:

- verifies PHP 8.2+ and the required CLI extensions;
- requires Composer dependencies and a local `.env`;
- refuses to run unless `APP_ENV=local` and `DB_CONNECTION=mysql`;
- generates `APP_KEY` only when it is empty;
- clears cached Laravel configuration;
- checks the configured database with `php artisan db:show`;
- starts `php artisan serve` on `127.0.0.1:8000`.

Keep the terminal running. Open:

```text
http://127.0.0.1:8000
```

The launcher does not run migrations or seeders automatically. This is deliberate: importing a database and rebuilding an empty database are different operations, and neither should happen silently.

To change the local port:

```bash
SANCTUM_PORT=8010 bash tools/xampp/serve.sh
```

Then verify that port:

```bash
SANCTUM_BASE_URL=http://127.0.0.1:8010 bash tools/xampp/verify-homepage.sh
```

## 5. Verify the homepage

With the server running, open a second terminal at the repository root:

```bash
bash tools/xampp/verify-homepage.sh
```

Expected result:

```text
PASS: GET / returned HTTP 200 and an HTML document.
```

This checks the real HTTP response from the Laravel development server. It does not claim that every homepage section, image, external integration, or interactive control is working; those require browser-level review.

### M027 acceptance checklist

- [ ] PHP CLI is 8.2+ and required extensions are loaded.
- [ ] Composer dependencies are installed.
- [ ] `.env` uses `APP_ENV=local` and the dedicated local MySQL database.
- [ ] MySQL connection succeeds.
- [ ] The migration state is understood and pending migrations are applied safely.
- [ ] `php artisan serve` starts on loopback.
- [ ] `GET /` returns HTTP 200 with HTML.
- [ ] Homepage images, CSS, JS, navigation, and console/network errors are reviewed in a browser.
- [ ] Public routes such as `/about`, `/service`, `/faq`, and `/contact` are checked.
- [ ] Unauthenticated access to protected user/admin areas redirects or rejects as expected.
- [ ] No production credentials or real personal/payment data are present in the local environment.

## 6. Run automated checks

The repository's verification entry point is:

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: composer check
```

This runs the static gates and PHPUnit suite against an in-memory SQLite database where the tests configure themselves accordingly.

For MySQL-specific verification, use a separate disposable database (for example, `sanctum_scan_test`), never the imported working copy. The CI workflow documents the MySQL sequence, including migration, route-introspection seeding, route registry, rollback/re-apply, and the MySQL-only settlement concurrency test.

## 7. Troubleshooting

| Symptom | Likely cause | Action |
|---|---|---|
| `could not find driver` | CLI PHP lacks `pdo_mysql` | Enable `pdo_mysql` for the same PHP binary used by Artisan |
| Access denied for user | Incorrect local MySQL credentials | Update `DB_USERNAME` and `DB_PASSWORD` |
| Unknown database | Local schema has not been created | Create `sanctum_scan_local` in phpMyAdmin |
| Migration table missing | Backup did not include Laravel migration history, or schema was never migrated | Confirm the correct import; only migrate a known empty/new local schema |
| HTTP 403 | Production host guard rejected the request | Confirm `APP_ENV=local` and use `127.0.0.1` or `localhost` |
| HTTP 503 | Database check failed | Inspect XAMPP MySQL status, `.env`, and `storage/logs/laravel.log` |
| HTTP 500 | Application/view data or dependency error | Inspect `storage/logs/laravel.log`; confirm the imported database includes core settings and website content |
| Missing images/assets | Public storage or seeded assets were not imported | Check the asset files and run `php artisan storage:link` if the project uses the public storage disk |

## Security notes

- Never commit `.env`, SQL dumps, database backups, provider credentials, or production secrets.
- Use a dedicated local database and sanitized/synthetic data.
- Do not expose the local development server to a public network.
- Do not use the default seeder credentials outside a disposable local environment.
- Do not run destructive migration commands against an imported or production database.
