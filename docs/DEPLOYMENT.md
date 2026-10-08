# Deployment

> On cPanel or other shared hosting, follow [CPANEL.md](CPANEL.md) instead.
> Errors and fixes: [TROUBLESHOOTING.md](TROUBLESHOOTING.md).

## Requirements

* PHP 8.2+ with `pdo_mysql`, `openssl`, `mbstring`, `intl`, `redis` (phpredis), `curl`
* MySQL 8 / MariaDB 10.6+ (InnoDB; row locks are required for verification safety)
* Redis 6+ for cache, nonces, rate limits and the queue
* Nginx (or another reverse proxy) with TLS
* Supervisor or systemd for the queue worker, and cron for the scheduler

## 1. Configuration

```bash
cp .env.example .env
php artisan key:generate
```

* `APP_KEY` encrypts client secrets, webhook secrets and merchant PSP credentials.
  **Back it up separately from the database.** If you lose it, every stored secret becomes
  unreadable. Never rotate it casually: data encrypted with the old key would have to be
  re-encrypted first.
* `APP_URL` must be the public HTTPS origin (e.g. `https://tech-kala.com`). It is used to
  build the `/pay/...` URLs and the PSP callback URLs.
* `TRUSTED_PROXIES` must list your load balancer or proxy addresses. Without it HTTPS is not
  detected, and HTTPS is enforced in production.
* `APP_DEBUG=false`, `APP_ENV=production`, `GATEWAY_SANDBOX_ENABLED=false`,
  `PAYMENTS_ALLOW_INSECURE_URLS=false`.
* Branding (white-label): `APP_NAME` is shown on the payment page and admin panel.
  `WEBHOOK_HEADER_PREFIX` (default `X-Webhook-`) and `WEBHOOK_USER_AGENT` control webhook
  headers. Changing the prefix after clients are live breaks their signature checks, so
  coordinate it with them.
* Never commit `.env`. Inject secrets from your secret store or deployment system.

## 2. Database

```sql
CREATE DATABASE techkala_payments CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'techkala_payments'@'10.%' IDENTIFIED BY '<strong password>';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES, DROP ON techkala_payments.* TO 'techkala_payments'@'10.%';
```

Use a separate, least-privilege user for the application if migrations run under a
different account.

```bash
php artisan migrate --force
php artisan db:seed --class=GatewayProviderSeeder --force   # providers are seeded DISABLED
php artisan admin:create ops@tech-kala.com
```

## 3. Build and caches

```bash
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Re-run these after each deploy, along with `php artisan migrate --force`.

## 4. Queue worker

Webhooks are delivered on the `webhooks` queue. Payment confirmation does **not** depend on
the worker. If the worker is down, payments still finalize and webhooks are delivered once it
recovers.

```ini
; /etc/supervisor/conf.d/techkala-payments-worker.conf
[program:techkala-payments-worker]
command=php /var/www/techkala-payments/artisan queue:work redis --queue=webhooks,default --sleep=1 --tries=1 --max-time=3600
numprocs=2
autostart=true
autorestart=true
stopwaitsecs=60
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/techkala-payments/worker.log
```

Run `php artisan queue:restart` after every deploy.

## 5. Scheduler

```cron
* * * * * www-data cd /var/www/techkala-payments && php artisan schedule:run >> /dev/null 2>&1
```

| Command | Frequency | Purpose |
|---------|-----------|---------|
| `payments:expire` | every minute | Expire unpaid payments past `expires_at` (sends `payment.expired`) |
| `payments:reconcile` | every 5 min | Re-verify interrupted verifications; retry AsanPardakht settlement |
| `webhooks:dispatch-due` | every minute | Dispatch due webhook retries; recover deliveries stuck in `processing` |

## 6. Redis

Use Redis for `CACHE_STORE`, `PAYMENTS_NONCE_STORE` and `QUEUE_CONNECTION`. All app servers
must share the same Redis, otherwise nonce replay protection and rate limits are per server.
Enable persistence (AOF) so queued webhooks survive a restart. The `webhooks:dispatch-due`
command re-dispatches anything that is lost anyway.

## 7. HTTPS and Nginx

```nginx
server {
    listen 443 ssl http2;
    server_name tech-kala.com;
    ssl_certificate     /etc/ssl/tech-kala.com/fullchain.pem;
    ssl_certificate_key /etc/ssl/tech-kala.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;

    root /var/www/techkala-payments/public;
    index index.php;
    client_max_body_size 64k;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param HTTPS on;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
    location ~ /\.(?!well-known) { deny all; }
}
server { listen 80; server_name tech-kala.com; return 301 https://$host$request_uri; }
```

Consider restricting `/admin` to an office VPN or IP allow-list at the proxy.

PSP callbacks arrive through the customer's browser, so `/api/v1/gateways/*/callback/*` must
be publicly reachable. Some Iranian PSPs also require the callback domain to be registered
with them and/or a server IP to be whitelisted for API calls. Arrange this with each PSP.

## 8. Enabling a PSP

Per-PSP requirements and the lessons from going live are in [PROVIDERS.md](PROVIDERS.md).

1. Get test credentials from the PSP, register the callback domain **and the server's IP**
   with them, and check from the server that the PSP API answers (`curl`).
2. In the admin panel, open **Providers → Configure** and set any non-secret settings
   (e.g. `{"sandbox": true}` for ZarinPal, or endpoint overrides).
3. Create a merchant for a test client with the PSP credentials and use **Test credentials**.
4. Enable the provider and run a real low-value payment end to end: create → pay → callback
   → verify (→ settlement for AsanPardakht). Check the payment timeline in the admin panel.
5. Only then switch to production credentials or settings and onboard real clients.

## 9. Backups

* Daily encrypted MySQL backups (`mysqldump --single-transaction`) with point-in-time
  recovery (binlogs). The `payments`, `payment_attempts`, `payment_events` and
  `webhook_deliveries` tables are financial records: retain them according to your
  accounting and legal requirements.
* Back up `APP_KEY` separately (e.g. in your secret manager). A database backup is useless
  for secrets without it, which is intended.
* Test restores regularly.

## 10. Logs

* `LOG_CHANNEL=stack`, `LOG_STACK=daily`, `LOG_DAILY_DAYS=30`. Laravel rotates the daily
  files itself. If you use `single`, configure logrotate:

```
/var/www/techkala-payments/storage/logs/*.log {
    daily
    rotate 30
    compress
    missingok
    notifempty
    copytruncate
}
```

* Every log line carries the `request_id`. Search by the `X-Request-Id` a client reports.
* A Monolog processor redacts secrets and card numbers, but still treat logs as sensitive.

## 11. Health and monitoring

* `GET /up` returns the Laravel health check.
* Alert on: payments stuck in `callback_received`/`verifying` (dashboard "Awaiting
  verification"), failed webhook deliveries, queue backlog size, and spikes of
  `client.auth_failed` in the audit log.
