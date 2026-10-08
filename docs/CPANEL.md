# Installing on cPanel (shared hosting)

This is the procedure that was used to put the service live on `tech-kala.com`, next to an
existing WordPress site on the same cPanel account (AlmaLinux, cPanel/WHM, MariaDB 10.11,
PHP 7.4 default with EA-PHP 8.3 installed, DNS on Cloudflare). It needs no Redis and no
Supervisor. Replace `USER` (the cPanel account, e.g. `techkala`) and the domain with your own.

Problems you may hit along the way are collected in [TROUBLESHOOTING.md](TROUBLESHOOTING.md).

## 0. Choose a layout

| Layout | Public URL | When |
|--------|-----------|------|
| **Sub-directory** | `https://example.com/payment` | Works without any DNS change. **Used in production.** |
| **Subdomain** | `https://pay.example.com` | Cleanest, but needs a DNS record. If DNS is on Cloudflare, you need Cloudflare access. |

In both cases the **project lives outside `public_html`** (`/home/USER/paymentgateway`), so
`.env`, the code and the logs are never reachable from the web. Only a tiny front controller
is exposed.

```
/home/USER/
├── public_html/              ← main website (unchanged)
│   └── payment/              ← sub-directory layout: only .htaccess + index.php
└── paymentgateway/           ← this project
    ├── public/               ← document root for the subdomain layout
    ├── .env
    └── ...
```

## 1. Work as the account user, not root

If you log in to SSH as `root`, switch to the cPanel user before doing anything else. Files
created by root cannot be written by the website (logs, cache) and break the app. cPanel users
often have `noshell`, so give the shell explicitly:

```bash
su -s /bin/bash - USER
```

## 2. Use PHP 8.3 on the command line

The server's default `php` may be older (here: 7.4). Laravel 11 needs 8.2+. Use the EA-PHP
binary for this session:

```bash
ls /opt/cpanel/ | grep ea-php                        # which versions exist
export PATH=/opt/cpanel/ea-php83/root/usr/bin:$HOME/bin:$PATH
php -v                                               # must show 8.2+ (8.3.x)
php -m | grep -ciE "^(dom|iconv|filter|hash|json|pcre|session)$"   # must print 7
php -m | grep -iE "pdo_mysql|mbstring|openssl|curl|fileinfo|tokenizer|xml|ctype"
```

`intl` and `sodium` are **not** required. Run the `export PATH=...` line again in every new SSH
session.

## 3. Get the code

```bash
cd ~
git clone -b claude/cool-mccarthy-ojh4ad https://github.com/alirezamshk/paymentgateway.git
cd paymentgateway
```

## 4. Composer

Some servers cannot reach `getcomposer.org` (connection timeout), and `allow_url_fopen` may
be off. GitHub and Packagist usually still work, so download Composer from GitHub releases:

```bash
mkdir -p ~/bin
curl -sSL -o ~/bin/composer https://github.com/composer/composer/releases/download/2.8.12/composer.phar
chmod +x ~/bin/composer
composer --version
composer install --no-dev --optimize-autoloader
```

If Packagist is unreachable too (`curl -I https://repo.packagist.org/packages.json` fails),
run `composer install --no-dev` on another machine and upload the project including `vendor/`.

## 5. Database

In cPanel use **Database Wizard** (or **Manage My Databases**; older themes call it
**MySQL Databases**):

1. Create database `payments` → full name `USER_payments`.
2. Create user `payuser` → full name `USER_payuser`.
3. Grant **ALL PRIVILEGES** on the database to the user.

Typing or pasting a generated password into a terminal prompt is error-prone (`Access
denied` later). The reliable way is to let the server generate the password, set it with
cPanel's `uapi`, and write it into `.env` in one go - see step 6.

## 6. Configure `.env`

As the account user, inside `~/paymentgateway`:

```bash
cat > .env <<'EOF'
APP_NAME="Tech-Kala Payments"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_TIMEZONE=UTC
APP_URL=https://example.com/payment
APP_LOCALE=en
ADMIN_LOCALE=fa
APP_FALLBACK_LOCALE=en
APP_MAINTENANCE_DRIVER=file
TRUSTED_PROXIES=

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_DAILY_DAYS=30
LOG_LEVEL=info

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=USER_payments
DB_USERNAME=USER_payuser
DB_PASSWORD=__SET_ME__

SESSION_DRIVER=database
SESSION_LIFETIME=60
SESSION_ENCRYPT=true
SESSION_PATH=/payment
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=strict

CACHE_STORE=database
CACHE_PREFIX=tkpay_
QUEUE_CONNECTION=database
QUEUE_WORK_VIA_SCHEDULER=true
PAYMENTS_NONCE_STORE=database
PAYMENTS_DEFAULT_CURRENCY=IRR
PAYMENTS_ALLOW_INSECURE_URLS=false

WEBHOOK_HEADER_PREFIX=X-Webhook-
GATEWAY_SANDBOX_ENABLED=false
MAIL_MAILER=log
EOF
chmod 600 .env
php artisan key:generate
```

* Subdomain layout: `APP_URL=https://pay.example.com` and `SESSION_PATH=/`.
* `QUEUE_WORK_VIA_SCHEDULER=true` lets cron run the webhook worker (no Supervisor needed).
* If the site is proxied by Cloudflare (orange cloud), set `TRUSTED_PROXIES=*`, otherwise HTTPS
  is not detected and API calls fail with `HTTPS_REQUIRED`.

**Database password** - as **root** (exit the user shell first), generate, set and store it:

```bash
cd /home/USER/paymentgateway
NEWPW=$(openssl rand -hex 16)
uapi --user=USER Mysql set_password user=USER_payuser password="$NEWPW" | grep -E "^ *(status|errors)" -A1   # status: 1
sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD='$NEWPW'/" .env
unset NEWPW
chown USER:USER .env && chmod 600 .env
su -s /bin/bash - USER
```

Back as the user:

```bash
cd ~/paymentgateway && export PATH=/opt/cpanel/ea-php83/root/usr/bin:$HOME/bin:$PATH
php artisan db:show | head -8           # shows MariaDB/MySQL version and the database
grep APP_KEY .env                       # store this value in a password manager
```

**Back up `APP_KEY`.** It encrypts client secrets, webhook secrets and PSP credentials;
without it they cannot be recovered.

## 7. Tables, admin user, caches

```bash
php artisan migrate --force --seed
php artisan admin:create admin@example.com --name="Admin"    # asks for a 12+ char password
chmod -R 775 storage bootstrap/cache
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan about --only=environment     # PHP 8.3, production, debug OFF, correct URL
```

## 8. Expose the application

### Sub-directory (used in production)

The main site keeps its own PHP version (7.4 here). The folder gets **its own `.htaccess`
with an EA-PHP 8.3 handler**, and a front controller that loads the project from outside
`public_html`. A real folder is used rather than a symlink, so `FollowSymLinks` restrictions
do not matter.

Check first that the path is free and look at the main site's handler line:

```bash
ls -ld ~/public_html/payment 2>/dev/null && echo "ALREADY EXISTS - choose another name"
grep -n "AddHandler" ~/public_html/.htaccess
```

Then create it (adjust `ea-php83` if the handler naming on your server differs):

```bash
mkdir ~/public_html/payment

cat > ~/public_html/payment/.htaccess <<'EOF'
# PHP 8.3 for the payment service only (the main site keeps its own version)
<IfModule mime_module>
  AddHandler application/x-httpd-ea-php83 .php .php8 .phtml
</IfModule>

<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>
    RewriteEngine On
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
EOF

cat > ~/public_html/payment/index.php <<'EOF'
<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));
$base = '/home/USER/paymentgateway';

if (file_exists($maintenance = $base.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $base.'/vendor/autoload.php';

(require_once $base.'/bootstrap/app.php')->handleRequest(Request::capture());
EOF

chmod 755 ~/public_html/payment
chmod 644 ~/public_html/payment/.htaccess ~/public_html/payment/index.php
```

The folder's own `RewriteEngine On` means WordPress's rewrite rules in the parent `.htaccess`
do not apply inside it. `SESSION_PATH=/payment` keeps the admin cookies separate from the main
site's cookies.

### Subdomain

1. **Domains → Create A New Domain**: `pay.example.com`, untick *Share document root*,
   document root `paymentgateway/public`.
2. **MultiPHP Manager**: set PHP 8.3 for the subdomain only.
3. **DNS**: if the domain's nameservers are elsewhere (check with `dig +short NS example.com`,
   e.g. `*.ns.cloudflare.com`), add `A pay → <server IP>` there (Cloudflare: *DNS only*, grey
   cloud). cPanel's local zone is not used by the internet in that case, and AutoSSL fails
   with *"does not resolve to any IP addresses"* until the record exists.
4. **SSL/TLS Status → Run AutoSSL**.

### Check

```bash
curl -sS -o /dev/null -w "up: %{http_code}\n"    https://example.com/payment/up
curl -sS -o /dev/null -w "admin: %{http_code}\n" https://example.com/payment/admin/login
curl -sS -o /dev/null -w "main site: %{http_code}\n" https://example.com/
```

All three must be `200`.

## 9. Cron

As the user (keeps any existing jobs):

```bash
crontab -l 2>/dev/null
(crontab -l 2>/dev/null; echo "* * * * * /opt/cpanel/ea-php83/root/usr/bin/php /home/USER/paymentgateway/artisan schedule:run >> /dev/null 2>&1") | crontab -
crontab -l
```

Use the **full path of PHP 8.3** - cron does not use your `PATH`. Verify:

```bash
php artisan schedule:list          # payments:expire, payments:reconcile, webhooks:dispatch-due, queue:work
php artisan schedule:run           # every line must end in DONE
```

and, as root, that cron really runs it every minute:

```bash
grep USER /var/log/cron | tail -3   # ... CMD (/opt/cpanel/ea-php83/... schedule:run ...)
```

## 10. First payment (smoke test)

1. Admin panel `https://example.com/payment/admin` → **Providers**: enable the PSP you will
   test (for ZarinPal keep `{"sandbox": true}` for a no-money test).
2. **Clients → New client** (e.g. *Test Site*, return URL `https://example.com/`). Copy the
   X-Client-Id (30 chars), client secret (69 chars) and webhook secret: they are shown once.
3. On the client page **Add merchant** with the PSP credentials, mark it default, then
   **Test credentials**.
4. Create a payment with the bundled test client. On the server itself, load the newest
   credential straight from the database to avoid copy/paste errors:

```bash
cd ~/paymentgateway
export TK_BASE_URL=https://example.com/payment
export TK_KEY_ID=$(php artisan tinker --execute='echo App\Models\ClientCredential::where("status","active")->latest("id")->value("key_id");')
export TK_SECRET=$(php artisan tinker --execute='echo App\Models\ClientCredential::where("status","active")->latest("id")->first()->secret();')
php scripts/test-client.php create          # expect HTTP 201 and "status": "pending"
```

5. Open the returned `payment_url` in a browser (VPN **off** - Shaparak gateways reject foreign
   IPs), pay, and you are sent to the return URL with `status=paid`.
6. Confirm the authoritative state (the redirect is only a hint):

```bash
php scripts/test-client.php status pay_...  # "status": "paid", reference_number set
```

7. In the admin panel open the payment: the **Timeline** shows every step
   (created → gateway.requested → pending → redirected → callback → verifying → paid).

If `status` is `failed` right after `create`, the PSP token request failed. See the reason:

```bash
php artisan tinker --execute='$a=App\Models\Payment::latest("id")->first()->latestAttempt; echo json_encode(["error_code"=>$a->error_code,"error_message"=>$a->error_message], JSON_UNESCAPED_UNICODE),"\n";'
```

and look it up in [TROUBLESHOOTING.md](TROUBLESHOOTING.md).

## Updating

```bash
su -s /bin/bash - USER            # if you are root
cd ~/paymentgateway
export PATH=/opt/cpanel/ea-php83/root/usr/bin:$HOME/bin:$PATH
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
* Logs: `~/paymentgateway/storage/logs/laravel-YYYY-MM-DD.log` (30 days kept).
* Once the new service is live, disable any legacy payment scripts left in `public_html`.
