# Architecture

## Domain model

```
Client (site)  ──< ClientCredential   (HMAC keys, encrypted, rotatable)
   │
   ├──< Merchant ──> GatewayProvider   (PSP credentials encrypted; one default per client)
   │
   └──< Payment ──< PaymentAttempt     (one per PSP token request)
            ├──< PaymentEvent          (append-only timeline)
            └──< WebhookDelivery       (signed, retried)
AuditLog                               (auth failures, admin & client actions)
```

* Every client-facing id is opaque: `cli_`, `mer_`, `pay_`, `att_`, `whd_`, `tkc_`, each
  followed by a lowercase ULID. Sequential database ids are never exposed.
* All client API queries go through the authenticated client
  (`$client->payments()`, `$client->merchants()`). A record that belongs to another client
  returns the same 404 as a record that does not exist.

## Code layout

| Path | Responsibility |
|------|----------------|
| `app/Http/Middleware/AuthenticateClient.php` | HMAC auth: timestamp window, signature (constant time), nonce replay, audit |
| `app/Http/Middleware/AssignRequestId.php` | `X-Request-Id` for every request, shared with logs, events, errors and webhooks |
| `app/Http/Controllers/Api/V1/*` | Thin controllers: Payment API, Merchant API, PSP callback |
| `app/Http/Controllers/Web/PaymentPageController.php` | `/pay/{payment_id}` customer page |
| `app/Http/Controllers/Admin/*` | Admin panel |
| `app/Payments/PaymentService.php` | Create, idempotency, new attempts, cancel |
| `app/Payments/VerificationService.php` | Callback handling, verification claim, finalization, settlement |
| `app/Payments/PaymentStateMachine.php` | **The only code that changes `payments.status`** |
| `app/Payments/MerchantResolver.php` | Tenant-scoped merchant selection |
| `app/Gateways/Contracts/*` | `GatewayInterface` plus optional capabilities (`SupportsSettlement`, `SupportsRefund`, `SupportsCredentialCheck`) |
| `app/Gateways/Adapters/*` | One class per PSP. All PSP-specific code lives here. |
| `app/Gateways/GatewayManager.php` | Provider code → adapter registry (`config/gateways.php`) |
| `app/Webhooks/*`, `app/Jobs/DeliverWebhook.php` | Webhook enqueue, signing, delivery and retry |
| `app/Support/SensitiveData.php` | Redaction and PAN masking for logs and stored PSP payloads |

## Payment state machine

```
created ──▶ pending ──▶ redirected ──▶ callback_received ──▶ verifying ──▶ paid
   │           │             │                ▲      │            │
   │           │             │                └──────┼────────────┤ (transient PSP error)
   ▼           ▼             ▼                       ▼            ▼
 failed / cancelled / expired                     failed       failed
 failed ──▶ pending   (only via explicit "new_attempt")
```

Transitions are defined in `PaymentStatus::allowedTransitions()`. The state machine enforces
them, writes a `payment_events` row for each one, and queues the matching webhook in the same
database transaction. `paid`, `cancelled` and `expired` are terminal, so a paid payment can
never go back to pending.

## Concurrency and idempotency

* **Create.** `UNIQUE(client_id, order_id)` and `UNIQUE(client_id, idempotency_key)`. When two
  concurrent creates race, the loser catches the unique violation and returns the winner's
  payment. A request hash detects a reused key or order with different parameters. The PSP
  token call happens outside any transaction, so no lock is held during network I/O.
* **Callback and verify.** Verification is *claimed*: inside a transaction holding a row
  lock (`SELECT ... FOR UPDATE`), the payment moves to `verifying`. Only the claimer calls
  the PSP. Concurrent callbacks see `verifying` or a final state and back off. The result is
  written in a second locked transaction that re-checks the payment is still `verifying`.
  This results in one PSP verify call, one `paid` transition and one `payment.succeeded`
  webhook per payment, no matter how many callbacks arrive.
* **Crashes.** A payment stuck in `verifying` for longer than
  `PAYMENTS_STALE_VERIFICATION_SECONDS` can be reclaimed. Transient PSP errors return the
  payment to `callback_received`. `payments:reconcile` (every 5 min) retries both cases.
  So does `POST /payments/{id}/verify`.
* **Webhooks.** `webhook_deliveries.dedupe_key` is unique, so each event is created at most
  once per payment (`pending` and `failed` once per attempt). A worker claims a delivery with
  a conditional `UPDATE ... WHERE status='pending'`, so a delivery is never sent by two
  workers at once and a delivered one is never re-sent. Receivers still de-duplicate on
  `X-Webhook-Delivery-Id`, because at-least-once delivery is inherent to HTTP.
* **Payment status never depends on webhook delivery.**

### Late callbacks

Callbacks are processed only for the latest attempt, and only while the payment is in
`pending`, `redirected` or `callback_received`. A callback that arrives after expiry or
cancellation is recorded as an event but not verified. Iranian PSPs (Shaparak) automatically
reverse transactions that the merchant does not verify, so the customer's money is returned.
This is why the default TTL (60 min) is well above typical PSP session lifetimes.

## Money convention

* Amounts are `BIGINT UNSIGNED` integers. Floating point is never used.
* The unit is the payment's `currency`: **IRR = Rial**, **IRT = Toman** (1 IRT = 10 IRR).
* A payment keeps its currency for its whole life. API responses and webhooks always use
  the payment's own currency.
* When a PSP requires a specific unit (Sepehr and AsanPardakht take Rials; Sepordeh takes
  Tomans by default; ZarinPal accepts either), the adapter converts with
  `App\Support\Money::convert()`. The conversion must be exact, otherwise the attempt fails
  with `AMOUNT_NOT_CONVERTIBLE`. Nothing is ever rounded.

## Security summary

* HMAC-SHA256 client auth with ±300 s timestamps, nonce replay protection (Redis `add`),
  constant-time comparison, per-IP and per-client rate limits, and audit logging of failures.
  `Referer`, `Origin` and `User-Agent` are never trusted.
* Client secrets, webhook secrets and merchant credentials are encrypted at rest with
  Laravel's `encrypted` casts (AES-256-CBC + HMAC, `APP_KEY`). They are never serialized
  into API responses or logs. HMAC needs the shared secret itself, so secrets are encrypted
  rather than hashed.
* Card data: only masked PANs (`603799******1234`) are stored. CVV and full PANs never are.
  A Monolog processor redacts secrets and Luhn-valid PANs in every log line. Stored PSP
  payloads are masked the same way.
* HTTPS is enforced in production (HSTS; plain-HTTP API calls are rejected). Security
  headers and a strict CSP are set (the payment page uses a nonce-based script and a
  `form-action` limited to the PSP origin). Admin cookies are secure, HttpOnly and
  SameSite=strict, and the admin login is throttled.
* Webhook endpoints must be public HTTPS. Private and reserved IP ranges are refused (SSRF)
  and redirects are not followed.
* Eloquent and the query builder are used throughout (parameterized SQL), with strict
  validation on every input.

## Admin test payments

`PaymentService::createTest()` runs a real PSP round trip through one merchant (disabled ones
included), flagged `payments.is_test`. Test payments send no webhooks (`WebhookService`), are not
credited to the ledger and are excluded from the sales report and dashboard totals; the PSP returns
the admin to `/pay/{id}/test-result`.

## Removing a merchant

`payments` and `payment_attempts` keep a restricting foreign key to their merchant, so history is
never lost. `MerchantService::delete()` hard-deletes a merchant without payments and otherwise
archives it (soft delete, disabled, credentials wiped); `Payment::merchant()` loads archived
merchants too. Deletion is refused while a payment on the merchant is not final (its callback and
verify still need the credentials) and for the default merchant while the client has others.

## Settlement

For clients whose payments land in Tech-Kala's own account, an append-only ledger
(`ledger_entries`, Rials, signed) records what Tech-Kala owes each client:

* the paid transition (inside `PaymentStateMachine`, same transaction) adds `+amount` (`payment`)
  and, when configured, `-commission` (`commission` = floor(amount × bps / 10000) + fixed, capped
  at the amount). `UNIQUE(payment_id, type)` makes crediting idempotent;
* an admin records a manual bank transfer as a `payout` (row-locked per client, cannot exceed the
  available balance);
* corrections are `adjustment` entries; entries are never edited or deleted.

`available_at` = paid time + the client's hold period; the payable balance only counts entries
whose `available_at` has passed. `settlement:backfill` credits paid payments created before the
ledger existed.

## Sales report

`App\Reports\SalesReport` aggregates paid payments (Rials) per day / week / month and per provider
or client, in Tehran time; Persian uses Jalali months and Saturday weeks, English Gregorian months
and Monday weeks. `StackedColumnChart` turns it into SVG geometry rendered by
`admin/reports/sales.blade.php` (no JavaScript, so the admin CSP stays strict). Each series keeps a
fixed colour slot by entity id; beyond 7 series the rest fold into "Other".

## White-label

The brand name (`APP_NAME`), the public origin (`APP_URL`) and the webhook header prefix
(`WEBHOOK_HEADER_PREFIX`, default `X-Webhook-`, see `App\Webhooks\WebhookHeaders`) are all
configuration. The same code can run as several independent branded instances.

## Adding a PSP

1. Create `app/Gateways/Adapters/FooGateway.php`, extending `AbstractGateway`. Implement
   `requiredCredentials()`, `createPayment()`, `callbackMatchesAttempt()` and
   `verifyPayment()`. Implement `SupportsSettlement` / `SupportsRefund` /
   `SupportsCredentialCheck` only if the PSP really supports them.
2. Register it in `config/gateways.php`: `'foo' => FooGateway::class`.
3. Insert a `gateway_providers` row with `code = 'foo'` (seeder or admin).
4. Add `tests/Feature/Gateways/FooGatewayTest.php` with faked HTTP responses.

The Payment API, state machine, callbacks and webhooks do not change.

## Refunds

`SupportsRefund` exists as a contract but no adapter implements it yet. Refund and reverse
semantics differ per PSP (same-day reverse versus refund, partial versus full), so each one
must be validated against the PSP sandbox before it is enabled.
