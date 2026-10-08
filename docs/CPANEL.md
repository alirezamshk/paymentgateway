# Installing on cPanel (shared hosting)

This guide installs the service next to an existing website on the same cPanel account,
without Redis or Supervisor.

Two layouts are supported:

| Layout | Public URL | Recommended |
|--------|-----------|-------------|
| **Subdomain** | `https://pay.example.com` | Yes. Cleanest: no conflicts with the main site's routes or `.htaccess`. |
| **Sub-directory** | `https://example.com/payment` | Works. The main site must not use the `/payment` path. |

In both cases the **project lives outside `public_html`**. Only its `public/` folder is
exposed, so `.env`, the code and the logs are never reachable from the web.

```
/home/USER/
├── public_html/              ← main website (unchanged)
│   └── payment  → symlink to ~/paymentgateway/public   (sub-directory layout only)
└── paymentgateway/           ← this project
    ├── public/               ← the only web-exposed folder
    ├── .env
    └── ...
```

## 1. PHP version and extensions

* **MultiPHP Manager**: set PHP **8.2 or newer** for the domain / subdomain.
* **Select PHP Version** / **PHP Extensions** (CloudLinux) or EasyApache: enable `pdo_mysql`,
  `mbstring`, `openssl`, `intl`, `sodium`, `curl`, `fileinfo`, `tokenizer`, `xml`, `ctype`.
* In **Terminal** (or SSH), find the PHP CLI binary for that version, e.g.
  `/opt/cpanel/ea-php83/root/usr/bin/php` or `/usr/local/bin/php`, and check it with `php -v`.
  Use that full path below wherever `php` appears if the default `php` is older.

## 2. Database

**MySQL Databases** → create a database (e.g. `USER_payments`) and a user with a strong
password. Then **Add User To Database** with **ALL PRIVILEGES**.

## 3. Get the code

In **Terminal**:

```bash
cd ~
git clone -b claude/cool-mccarthy-ojh4ad https://github.com/alirezamshk/paymentgateway.git
cd paymentgateway
composer install --no-dev --optimize-autoloader
```

* For a private repository, use **Git Version Control** in cPanel or clone with a GitHub
  access token.
* If `composer` is not on the PATH, try `/opt/cpanel/composer/bin/composer`.
* Without Terminal access: run `composer install --no-dev` on your own computer, zip the
  whole folder (including `vendor/`), upload it with **File Manager** to `/home/USER/`, and
  extract it there.

## 4. Configure `.env`

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` (File Manager → Edit):

```env
APP_NAME="Tech-Kala Payments"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://pay.example.com          # sub-directory layout: https://example.com/payment

DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=USER_payments
DB_USERNAME=USER_payuser
DB_PASSWORD=...

# No Redis on most shared hosts: use the database for everything
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database
PAYMENTS_NONCE_STORE=database
QUEUE_WORK_VIA_SCHEDULER=true            # cron runs the webhook queue worker every minute

GATEWAY_SANDBOX_ENABLED=false
ADMIN_LOCALE=fa                          # admin panel language: fa or en
```

If the site is behind Cloudflare or another proxy, set `TRUSTED_PROXIES=*`. Otherwise
HTTPS is not detected and API calls are rejected with `HTTPS_REQUIRED`.

**Save a copy of `APP_KEY` somewhere safe** (a password manager). Without it, the stored
secrets and PSP credentials cannot be decrypted.

## 5. Install the database and caches

```bash
php artisan migrate --force --seed
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan admin:create you@example.com
chmod -R 775 storage bootstrap/cache
```

## 6. Expose the `public/` folder

**Subdomain (recommended):** **Domains** (or **Subdomains**) → create `pay.example.com` and
set its **Document Root** to `/home/USER/paymentgateway/public`.

**Sub-directory:** in Terminal:

```bash
ln -s ~/paymentgateway/public ~/public_html/payment
```

If the host does not follow symlinks, create a folder `public_html/payment` instead, copy
`public/.htaccess` and `public/index.php` into it, and edit the two `__DIR__.'/../...'` paths
in that `index.php` to point at `/home/USER/paymentgateway/...`.

## 7. HTTPS

**SSL/TLS Status** → run **AutoSSL** for the domain or subdomain. Open
`https://pay.example.com/up`; it should return the health page.

## 8. Cron

**Cron Jobs** → add, every minute (`* * * * *`):

```
/usr/local/bin/php /home/USER/paymentgateway/artisan schedule:run >> /dev/null 2>&1
```

Use the PHP 8.2+ binary path from step 1. This single job expires old payments, re-verifies
interrupted payments, and (with `QUEUE_WORK_VIA_SCHEDULER=true`) delivers webhooks.

## 9. Verify

1. `https://pay.example.com/admin`: log in.
2. **Providers**: everything is disabled except the sandbox, which is disabled in production
   anyway.
3. Create a client and a merchant, enable one provider, and run a low-value real payment
   (see `DEPLOYMENT.md` → *Enabling a PSP*).

## Updating

```bash
cd ~/paymentgateway
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
```

## Notes

* Sub-directory installs: client sites must sign the **full** request path, including the
  folder, e.g. `/payment/api/v1/payments`.
* Webhooks are delivered within about a minute of a payment changing state (cron
  granularity). Payment status itself is updated immediately.
* Logs: `~/paymentgateway/storage/logs/`. The default `daily` channel keeps 30 days.
