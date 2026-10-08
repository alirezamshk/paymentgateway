# CLAUDE.md — working on this repository

Central, multi-tenant payment gateway for tech-kala.com (Laravel 11, PHP 8.3). Read
`README.md` and `docs/ARCHITECTURE.md` first; `docs/fa/README.md` is the operator guide in Persian.
The owner communicates in Persian — reply in Persian.

## Hard rules (from the original spec)
* Never use, call, redirect to or integrate with `tikpgate.com` or `tikp.site`.
* Do not touch the legacy payment code on the main site (`public_html`, e.g. `sepehr2`) unless asked.
* Never invent PSP credentials or endpoints. Only call a gateway "production-ready" after a real
  sandbox/production payment has been verified.
* Secrets (client secrets, webhook secrets, merchant credentials, `APP_KEY`) are never logged,
  printed or committed. `Referer` / `Origin` / `User-Agent` are never authentication.
* Only `PaymentStateMachine` changes `payments.status`. Money is integer (IRR/IRT), never float.
* The settlement ledger is append-only; corrections are new `adjustment` entries.

## Commands
```bash
composer install
vendor/bin/pint            # CI runs `pint --test`
php artisan test           # SQLite in-memory; phpunit.xml pins cache/queue/nonce stores
```
Every feature ships with tests in `tests/Feature` (gateways use `Http::fake`).

## Where things are
| Area | Code |
|------|------|
| Client API (HMAC) | `app/Http/Middleware/AuthenticateClient.php`, `app/Http/Controllers/Api/V1` |
| Payments, verify, state machine | `app/Payments/*` |
| PSP adapters | `app/Gateways/Adapters/*` (`config/gateways.php`) — see `docs/PROVIDERS.md` |
| Webhooks | `app/Webhooks/*`, `app/Jobs/DeliverWebhook.php` |
| Settlement ledger | `app/Settlement/LedgerService.php`, `Admin/SettlementController` |
| Sales report + chart | `app/Reports/SalesReport.php`, `app/Reports/StackedColumnChart.php`, `Admin/ReportController` |
| Admin UI (fa/en) | `resources/views/admin/*`, `lang/fa.json`, `app/Http/Middleware/SetAdminLocale.php`, `app/Support/Display.php` (Jalali, Tehran time) |
| Developer docs page (`/docs`, public unless `PAYMENTS_PUBLIC_DOCS=false`) | `app/Http/Controllers/Web/DocsController.php`, `resources/views/docs/index.blade.php` — keep in sync with `docs/openapi.yaml` |
| Customer pay page | `app/Http/Controllers/Web/PaymentPageController.php` (auto-redirect, `Referrer-Policy: origin` for Sepehr) |

Admin UI conventions: every new string goes in `lang/fa.json` (English is the key); the admin CSP
forbids inline JS, so charts are server-rendered SVG with `<title>` tooltips; amounts are wrapped
in `<span class="ltr">` for RTL.

## Production (as of 2026-10)
* cPanel, user `techkala`; code in `~/paymentgateway`, served at `https://tech-kala.com/payment`
  (sub-folder install, `docs/CPANEL.md`). PHP: `/opt/cpanel/ea-php83/root/usr/bin/php`.
  MariaDB; cache/session/queue/nonce on the database; cron runs `schedule:run` with
  `QUEUE_WORK_VIA_SCHEDULER=true`.
* Server deploys from `main`. Work happens on a feature branch, merged to `main`, then on the
  server: `git pull && php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache`
  (first deploy of the ledger also needs `php artisan settlement:backfill`).
* Verified live: Sepehr (real payment + v2moon billing-panel round trip with webhooks), Sepordeh
  (real payment), ZarinPal sandbox. Not yet verified live: AsanPardakht, ZarinPal production.

## Integrator docs
`docs/INTEGRATION.md` (developers), `docs/CLIENT_AGENT_BRIEF.md` and `docs/BILLING_PANEL_BRIEF.md`
(hand-off briefs for agents building the client side), `docs/openapi.yaml` (API reference).

## Open follow-ups
* v2moon uses client `cli_01m4d25bsahcpjrj3te9714rds` (formerly "test-site") with one active key and
  the merchant list from `GET /api/v1/merchants`; it should also send `customer` / `metadata`.
* The `ZarinPal Sandbox` merchant on that client must stay disabled (sandbox marks payments paid).
* Disable the test client and remove `tk-test.php` / legacy `sepehr2` files on the main site.
* Live-test AsanPardakht with a small amount before enabling it.
