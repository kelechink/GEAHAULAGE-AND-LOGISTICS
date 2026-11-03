# Deploying to FigureWeb cPanel

This guide deploys `backend/` (Laravel 11) to a cPanel shared-hosting account. It covers a staging subdomain first, then production on geahaulageandlogistics.com.

## 1. Check the server

In cPanel, go to **Select PHP Version** (or **MultiPHP Manager**) and set PHP **8.2 or newer** for the domain. Enable these extensions:

`pdo_mysql`, `mbstring`, `gd`, `zip`, `intl`, `bcmath`, `fileinfo`, `openssl`, `tokenizer`, `xml`, `ctype`, `curl`

Also confirm:

- SSH access (Terminal in cPanel, or SSH keys)
- Composer is available (`composer -V`). If not, build `vendor/` locally and upload it (see step 4b)
- Cron Jobs are available
- AutoSSL has issued a certificate for the domain and the staging subdomain

## 2. Create the database

In **MySQL Databases**:

1. Create a database, e.g. `cpuser_gea`.
2. Create a user with a strong password.
3. Add the user to the database with **All privileges**.

## 3. Lay out the files

Keep the app outside `public_html`, and point the domain's document root at the app's `public/` folder.

```
/home/cpuser/
├── gea-platform/            ← the repo (or just backend/)
│   └── backend/
│       └── public/          ← document root
└── public_html/             ← not used by the app
```

In **Domains** (or **Subdomains** for staging), set the document root to `gea-platform/backend/public`.

If the host can't change the document root of the main domain, replace `public_html` with a symlink:

```bash
mv ~/public_html ~/public_html.bak && ln -s ~/gea-platform/backend/public ~/public_html
```

## 4. Upload the code

### 4a. With SSH and git (preferred)

```bash
cd ~ && git clone https://github.com/kelechink/GEAHAULAGE-AND-LOGISTICS.git gea-platform
cd ~/gea-platform/backend
composer install --no-dev --optimize-autoloader
```

### 4b. Without Composer on the server

Locally, run `composer install --no-dev --optimize-autoloader` in `backend/`, zip the folder including `vendor/`, upload it with File Manager and extract it.

## 5. Configure the environment

```bash
cd ~/gea-platform/backend
cp .env.example .env
php artisan key:generate
```

Edit `.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://geahaulageandlogistics.com
APP_TIMEZONE=Africa/Lagos

DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=cpuser_gea
DB_USERNAME=cpuser_gea
DB_PASSWORD=...

QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database

# Email through Brevo (better delivery than cPanel mail). The username is the
# "SMTP login" on Brevo's SMTP & API page; the password is the SMTP key (xsmtpsib-...).
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_SCHEME=smtp
MAIL_USERNAME=xxxxxx@smtp-brevo.com
MAIL_PASSWORD=xsmtpsib-...
MAIL_FROM_ADDRESS=info@geahaulageandlogistics.com
MAIL_FROM_NAME="GEA Haulage and Logistics"

# Website assistant (free tiers). Without keys the chat still takes leads with a form.
GEMINI_API_KEY=...
GROQ_API_KEY=...

# Two-factor sign-in for /admin: always true outside a local demo
TWO_FACTOR_ENFORCED=true

# Monitoring and backups (see docs/backups-and-monitoring.md)
ALERT_EMAIL=admin@figureweb.ng
BACKUP_DISKS=local,offsite
BACKUP_SFTP_HOST=...
BACKUP_SFTP_USERNAME=...
BACKUP_SFTP_PASSWORD=...
```

Use `APP_URL=https://staging.geahaulageandlogistics.com` and a separate database for staging.

**Brevo sender domain:** in Brevo, go to Senders, Domains & Dedicated IPs → Domains and add `geahaulageandlogistics.com`. Add the DKIM, Brevo code and DMARC records it shows in cPanel → Zone Editor, then click Authenticate. Until the domain is authenticated, mail from info@, sales@ and billing@ may be refused or land in spam. Send a test with:

```bash
php artisan tinker --execute 'Mail::raw("Test from the GEA platform", fn ($m) => $m->to("you@example.com")->subject("GEA mail test"));'
```

If the server can't connect on port 587, ask the host to allow outgoing SMTP, or try `MAIL_PORT=2525`.

## 6. Migrate, seed and cache

```bash
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan db:seed --class=WebsiteContentSeeder --force
php artisan db:seed --class=PlacesSeeder --force
php artisan db:seed --class=KnowledgeSeeder --force
php artisan storage:link
php artisan filament:optimize
php artisan optimize
```

Create the first Super Admin (FigureWeb):

```bash
php artisan gea:make-admin "FigureWeb Admin" admin@figureweb.ng
```

It asks for the password. Do **not** run the full `DatabaseSeeder` in production: it creates demo users.

`WebsiteContentSeeder` adds the 8 services, FAQs and career roles from the v3 design. `PlacesSeeder` adds the checkpoint towns used by the update console and maps. `KnowledgeSeeder` builds the chat assistant's knowledge from the website copy. None of them overwrite rows staff have edited, so they are safe to run again after an update.

Then load GEA's real customers, trucks, drivers and routes with **Settings → Import data** (see the go-live checklist).

## 7. Cron (scheduler and queue)

In **Cron Jobs**, add one job that runs every minute:

```
* * * * * cd /home/cpuser/gea-platform/backend && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

Use the PHP binary path for the version you selected (`which php` over SSH, or e.g. `/opt/cpanel/ea-php83/root/usr/bin/php`).

The scheduler runs `queue:work --stop-when-empty` every minute, so queued jobs (emails, notifications) are processed without a long-running worker. It also runs tracker polling, stale-trip and overdue checks, the 7am digest, nightly backups and a heartbeat that `/health` reads. Run `php artisan schedule:list` to see them all.

## 8. Compression and caching

`public/.htaccess` turns on Brotli or gzip compression and one-year caching for versioned CSS, JS, fonts, images and videos. Check it works after deploying:

```bash
curl -sI -H "Accept-Encoding: gzip" https://geahaulageandlogistics.com/css/site.css | grep -iE "content-encoding|cache-control"
```

You should see `content-encoding: gzip` (or `br`) and `cache-control: public, max-age=31536000, immutable`. If not, ask the host to enable `mod_deflate` and `mod_headers`. On LiteSpeed, compression is usually on by default.

Mobile Lighthouse on the home page, measured locally with compression on: performance 99, accessibility 100, best practices 100, SEO 100, LCP about 2.0 s.

## 9. Permissions and security

```bash
chmod -R 775 storage bootstrap/cache
```

- `.env` must never be inside the document root. With the layout above it isn't.
- `APP_DEBUG=false` in production.
- Turn on **Force HTTPS Redirect** for the domain in cPanel.

## 10. Updating

```bash
cd ~/gea-platform && git pull
cd backend && composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --class=WebsiteContentSeeder --force
php artisan optimize:clear && php artisan optimize && php artisan filament:optimize
```

## 11. Backups and monitoring

The app backs up the database and uploads every night at 01:30 (Lagos), keeps 30 days and emails failures. Keep cPanel/JetBackup on as well. Set up the off-server copy, UptimeRobot and a restore test as described in `docs/backups-and-monitoring.md`.

## Moving to a VPS later

The app needs no changes. On a VPS, switch `QUEUE_CONNECTION` and `CACHE_STORE` to Redis if wanted, run `queue:work` under Supervisor, and optionally replace tracking polling with Laravel Reverb.
