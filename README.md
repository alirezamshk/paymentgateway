# Tech-Kala Payment Service

Central, multi-tenant payment gateway for `tech-kala.com`. External sites (**Clients**) create
payments through a signed REST API. Tech-Kala picks the Client's **Merchant**, talks to the
**PSP** through a pluggable adapter, verifies the result with the PSP, and notifies the Client
by signed webhook.

```
Client site ──HMAC API──▶ Tech-Kala ──▶ Merchant ──▶ Gateway adapter ──▶ PSP
                             ▲                                            │
                             └──── callback → verify with PSP ◀───────────┘
Client site ◀── signed webhook (queued, retried) ─── Tech-Kala
```

This is an independent system. It has no runtime, database or redirect dependency on any
legacy payment system.

## White-label

Nothing in the logic is tied to one brand or domain. Deploy as many independent instances as
you need, each with its own `.env`, database and `APP_KEY`:

| Setting | Controls | Default |
|---------|----------|---------|
| `APP_URL` | Public origin: payment page, PSP callback URLs, API base | - |
| `APP_NAME` | Brand name on the payment page and admin panel | `Tech-Kala Payments` in `.env.example` |
| `WEBHOOK_HEADER_PREFIX` | Webhook header names `{prefix}Event`, `{prefix}Delivery-Id`, `{prefix}Timestamp`, `{prefix}Signature` | `X-Webhook-` |
| `ADMIN_LOCALE` | Admin panel language: `fa` (Persian, RTL) or `en`. The API always answers in English. | `fa` |
| `WEBHOOK_USER_AGENT` | `User-Agent` of webhook requests | `PaymentService-Webhooks/1.0` |

Pick the domain before going live. Each payment stores its PSP callback URL when it is
created, so keep the old domain redirecting to the new one while payments are in flight.

## Stack

PHP 8.2+ · Laravel 11 · MySQL/MariaDB · Redis (cache, nonces, queue) · PHPUnit

## Documentation

| Document | Contents |
|----------|----------|
| [docs/INTEGRATION.md](docs/INTEGRATION.md) | For client sites: signing requests, creating payments, verifying webhooks (PHP/Node samples) |
| [docs/openapi.yaml](docs/openapi.yaml) | OpenAPI 3.1 reference: auth, payments, merchants, callbacks, webhooks, errors, idempotency |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Module layout, state machine, concurrency model, money convention, adding a PSP |
| [docs/CPANEL.md](docs/CPANEL.md) | Step-by-step install on cPanel / shared hosting (subdomain or sub-directory, no Redis/Supervisor) |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | Production setup: database, migrations, queue worker, scheduler, Redis, HTTPS, backups, logs |
| [docs/PROVIDERS.md](docs/PROVIDERS.md) | Per-PSP notes: credentials, endpoints, requirements (IP registration, Referer), verified status |
| [docs/TROUBLESHOOTING.md](docs/TROUBLESHOOTING.md) | Errors met during installation and operation, with causes and fixes |
| [docs/fa/README.md](docs/fa/README.md) | راهنمای فارسی: نصب، کار با پنل، اتصال سایت‌ها، خطاهای رایج |
| [scripts/test-client.php](scripts/test-client.php) | Dependency-free client for smoke-testing a deployment (create / status / verify / cancel) |

## Quick start (local)

```bash
composer install
cp .env.example .env
php artisan key:generate
# Local overrides in .env:
#   APP_ENV=local  APP_URL=http://127.0.0.1:8000  DB_CONNECTION=sqlite
#   CACHE_STORE=database  QUEUE_CONNECTION=sync  PAYMENTS_NONCE_STORE=database
#   GATEWAY_SANDBOX_ENABLED=true  PAYMENTS_ALLOW_INSECURE_URLS=true  SESSION_SECURE_COOKIE=false
touch database/database.sqlite
php artisan migrate --seed            # creates tables + gateway providers

php artisan admin:create you@example.com          # admin panel user (prompts for password)
php artisan client:create "Panel A" panel-a \
  --webhook-url=https://panel-a.example.com/hooks/tk \
  --return-url=https://panel-a.example.com/payment/result   # prints API key + secrets once

php artisan serve
```

Admin panel: `http://127.0.0.1:8000/admin`. Add a merchant for the client there. The
**Sandbox** provider needs no credentials and simulates a PSP, so you can run the whole flow
locally.

## Tests

```bash
php artisan test         # unit + feature suite (SQLite in memory)
vendor/bin/pint --test   # code style
```

The suite covers signing, the state machine, idempotency, merchant selection, tenant isolation
and IDOR, replay and timestamp attacks, rate limiting, duplicate and concurrent callbacks,
webhook signing, retry and backoff, the admin panel, and every PSP adapter (against faked PSP
HTTP responses).

## Provider status

| Provider | Adapter | Settlement | Automated tests | Verified against real PSP |
|----------|---------|------------|-----------------|---------------------------|
| ZarinPal (REST v4) | `ZarinPalGateway` | not required | yes (faked HTTP) | sandbox only (2026-10-08) |
| Sepehr / Saderat | `SepehrGateway` | not required (Advice) | yes (faked HTTP) | **yes - live payment verified (2026-10-08)** |
| Asan Pardakht (IPG REST v1) | `AsanPardakhtGateway` | yes (`/v1/Settlement`) | yes (faked HTTP) | **no** |
| Sepordeh | `SepordehGateway` | not required | yes (faked HTTP) | **no** |
| Sandbox (internal) | `SandboxGateway` | - | yes (end-to-end) | n/a |

> **Sepehr** has completed a real production payment end to end (token → bank page → callback →
> Advice → paid) on `tech-kala.com`. Sepehr requires the server IP to be registered and the payment page
> to send the registered domain as Referer (handled: the payment page uses `Referrer-Policy: origin`).
>
> **The other PSP integrations are not production-ready yet.** Each adapter follows the PSP's
> published API and is covered by automated tests, but none has been run against the real PSP
> sandbox or production environment. Before you enable a provider (they are seeded as
> *disabled*), run a real test transaction with that PSP's test credentials. Endpoints can be
> overridden per provider in the admin panel (`Providers → Configure`) without code changes.
> Sepordeh's API details in particular (amount unit, callback parameter names) must be
> confirmed against its current documentation.
