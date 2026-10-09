# Troubleshooting

Problems met while installing and running the service, with their causes and fixes. Every API
error carries a `request_id`; search for it in `storage/logs/` and in the admin **Audit log**.

## Installation

| Symptom | Cause | Fix |
|---------|-------|-----|
| `php -v` shows 7.x | Server default PHP is old | `export PATH=/opt/cpanel/ea-php83/root/usr/bin:$HOME/bin:$PATH` (every SSH session); use the full path in cron |
| `su: ... This account is currently not available` | cPanel user has `noshell` | `su -s /bin/bash - USER` |
| `Could not open input file: composer-setup.php` | `allow_url_fopen` is off, or `getcomposer.org` blocked | Download `composer.phar` from GitHub releases with `curl` (see [CPANEL.md](CPANEL.md) step 4) |
| `curl: (7) Failed to connect to getcomposer.org` | Outbound block to that host | Same as above |
| `SQLSTATE[HY000] [1045] Access denied for user` | Wrong password in `.env` (typing/pasting into a hidden prompt) | Generate and set the password with `uapi` and write it with `sed` (CPANEL.md step 6) |
| AutoSSL: *does not resolve to any IP addresses* | DNS is hosted elsewhere (e.g. Cloudflare), cPanel's zone is not authoritative | Add the `A` record at the DNS provider, or use the sub-directory layout |
| `/payment/...` shows WordPress 404 / PHP 7 errors | Folder has no own `.htaccess` / handler | Create `public_html/payment/.htaccess` with `AddHandler application/x-httpd-ea-php83` and the rewrite rules |
| Admin login loops back to the login page | Secure cookie over HTTP, or wrong `SESSION_PATH` | Use HTTPS; `SESSION_PATH` must match the folder (`/payment`) or `/` for a subdomain |
| `HTTPS_REQUIRED` on every API call | Behind a proxy/CDN, HTTPS not detected | `TRUSTED_PROXIES=<the proxy/CDN IP ranges>` (see CPANEL.md; avoid `*`), then `php artisan config:cache` |
| Config change has no effect | Config is cached | `php artisan config:cache` after every `.env` change |
| `grep: /var/log/cron: Permission denied` | Only root can read it | Run that check as root |

## API (client sites)

| Error code | Cause | Fix |
|------------|-------|-----|
| `AUTH_REQUIRED` | Missing `X-Client-Id` / `X-Timestamp` / `X-Nonce` / `X-Signature` | Send all four headers |
| `AUTH_INVALID_SIGNATURE` | Wrong secret, wrong key, or wrong path | Check lengths: key **30**, secret **69** characters (copy/paste mistakes are the usual cause). Sign the **full** path including the sub-directory (`/payment/api/v1/...`). Sign the exact body bytes you send. The audit log (`client.auth_failed`) shows whether the key id was found. |
| `AUTH_TIMESTAMP_EXPIRED` | Clock skew > 300 s | Sync the client server clock (NTP) |
| `AUTH_NONCE_REPLAYED` | Same nonce sent twice | Generate a new random nonce per request |
| `AUTH_CLIENT_DISABLED` | Client or credential disabled/revoked | Enable it / issue a new credential in the admin panel |
| `MERCHANT_NOT_FOUND` | No active default merchant, or `merchant_id` of another client | Set a default merchant for the client |
| `PROVIDER_UNAVAILABLE` | Provider disabled in **Providers** | Enable it |
| `ORDER_ALREADY_EXISTS` | Same `order_id` with different amount/params | Use a new `order_id`, or `new_attempt: true` for a failed payment |
| `VALIDATION_ERROR` | e.g. amount as float/string, `return_url` not HTTPS | See `error.details` |

## Payments

Check a failed payment's reason:

```bash
php artisan tinker --execute='$a=App\Models\Payment::where("public_id","pay_...")->first()->latestAttempt; echo json_encode(["error_code"=>$a->error_code,"error_message"=>$a->error_message,"response"=>$a->response_payload], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),"\n";'
```

| Symptom / code | Cause | Fix |
|----------------|-------|-----|
| `status: failed` right after create, `GATEWAY_UNAVAILABLE` | Server cannot connect to the PSP API (firewall, wrong port, wrong endpoint) | `curl` the PSP API from the server. For Sepehr use `https://sepehr.shaparak.ir/Rest/V1/...` - port 8081 refuses connections |
| `SEPEHR_-2` | Server IP not registered for the terminal | Ask Sepehr/Bank Saderat to register the server IP |
| Sepehr page: *عملیات درخواستی شما با خطا مواجه شده است (UrlReferrer)* | Browser sent no Referer from the registered domain | Fixed in the payment page (`Referrer-Policy: origin`); make sure the server runs the latest code and the domain in `APP_URL` is the one registered with Sepehr |
| PSP page refuses the customer | Customer uses a VPN / foreign IP | Disable VPN |
| `AMOUNT_NOT_CONVERTIBLE` | IRR amount not divisible by 10 for a Toman-based PSP (Sepordeh) | Send amounts that convert exactly, or use `IRT` |
| `AMOUNT_MISMATCH` | PSP confirmed a different amount | Manual review in the admin panel; payment is not marked paid |
| Stuck in `callback_received` / `verifying` | PSP verify timed out | Automatic retry by `payments:reconcile` (every 5 min) or **Re-run PSP verification** in the admin panel, or `POST /api/v1/payments/{id}/verify` |
| Redirect says `status=paid` but API says otherwise | The redirect is only a hint | Always trust `GET /api/v1/payments/{id}` or the signed webhook |

## Webhooks

| Symptom | Cause | Fix |
|---------|-------|-----|
| No webhook deliveries at all | Client has no webhook URL | Set it on the client (HTTPS) |
| Deliveries stay `pending` | Cron/queue not running | Check the cron line and `php artisan schedule:run`; `QUEUE_WORK_VIA_SCHEDULER=true` on cPanel |
| `Webhook endpoint is not allowed` | URL is not public HTTPS or resolves to a private IP | Use a public HTTPS URL |
| `failed` after 8 attempts | Receiver kept returning non-2xx | Fix the receiver, then **Retry** in Admin → Webhooks |
| Receiver rejects the signature | Wrong webhook secret, or body re-serialized before verifying | Verify `hex(HMAC-SHA256(secret, timestamp + "." + raw_body))` on the **raw** body; header names use `WEBHOOK_HEADER_PREFIX` (default `X-Webhook-`) |
