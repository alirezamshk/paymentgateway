# Task brief: connect our billing panel to the Tech-Kala Payment Service

> Give this whole document to the developer or coding agent that will implement it. It is
> self-contained. Credentials are **not** in this document: they are entered in the panel's
> settings by an administrator (section 1).

## 0. Goal

Our billing panel creates **invoices**. Add a payment method ("Online payment / پرداخت آنلاین")
that sends an invoice to the **Tech-Kala Payment Service** for payment and marks the invoice
paid when Tech-Kala confirms it. The panel never talks to a bank directly.

```
Customer opens an unpaid invoice → clicks "Pay online"
  → panel server: POST {BASE}/api/v1/payments   (order_id = invoice reference, amount = invoice total)
  → panel redirects the customer to the returned payment_url  (Tech-Kala page → bank)
  → customer pays at the bank; Tech-Kala verifies with the bank
  → Tech-Kala → panel webhook  payment.succeeded   (signed)  → panel marks the invoice PAID
  → Tech-Kala → customer browser → panel invoice page          (display only, NOT proof)
```

**Before writing code, inspect the panel's stack** (language, framework, how invoices and
payment methods/gateways are modelled, how settings and secrets are stored, how other payment
methods are integrated) and implement this as a payment method/module in the panel's existing
style. If the panel already has a payment-gateway plugin system, implement a plugin for it.

## 1. Settings (entered by an administrator, stored server-side)

Add a settings screen (or config entries) for this payment method:

| Setting | Example | Notes |
|---------|---------|-------|
| Enabled | yes/no | Show the "Pay online" button only when enabled |
| Base URL | `https://tech-kala.com/payment` | Includes the `/payment` folder. No trailing slash. |
| Client ID | `tkc_...` | **Exactly 30 characters**. Sent as `X-Client-Id`. |
| Client Secret | `tksk_...` | **Exactly 69 characters**. Used to sign requests. |
| Webhook Secret | `whsec_...` | Used to verify webhooks. |
| Amount unit of the panel | `toman` or `rial` | How invoice amounts are stored in the panel (see section 3a) |

Rules:

* Store the two secrets **encrypted** (or in the framework's secret store); never show them again
  after saving (show "configured"), never log them, never send them to the browser.
* Validate on save: Client ID length 30, Client Secret length 69, Base URL is `https://`.
  Most integration problems are copy/paste mistakes.
* On the settings screen, **display the panel's Webhook URL** (section 5) so the administrator
  can give it to Tech-Kala, and the **Return URL** pattern.
* Optional "Test connection" button: call `GET {BASE}/api/v1/merchants` (signed); `200` means the
  keys and signing work.

Tech-Kala's administrator must register for this panel:

* **Webhook URL**, e.g. `https://<panel-domain>/payments/techkala/webhook` (POST, HTTPS, public,
  CSRF-exempt, no login)
* **Default return URL**, e.g. `https://<panel-domain>/payments/techkala/return` (GET, HTTPS)

### Several gateways (optional)

One panel = one Tech-Kala client: **one** Client ID / Secret, **one** Webhook Secret and **one**
Webhook URL, no matter how many gateways it offers. Each gateway is a *merchant* (`mer_...`) that
Tech-Kala adds under that same client. Do not ask for a second key pair per gateway.

* **Settings design (required):** store the connection (Base URL, Client ID, Client Secret,
  Webhook Secret) **once**, on its own "Tech-Kala connection" screen. Gateways must NOT be separate
  connection entries that each ask for keys: adding a gateway later must never require re-entering
  or resetting any secret (secrets are shown by Tech-Kala only once, so re-entry forces a reset).
* Gateways are loaded, not typed: a "Sync gateways" button (and/or on each checkout) calls
  `GET {BASE}/api/v1/merchants`, which lists them (`merchant_id`, `name`, `provider`,
  `is_default`, `status`). Store only the active ones; the administrator may rename, reorder or
  hide them locally. When Tech-Kala adds a gateway, one sync shows it, with no other change.
  Offer them as choices (e.g. let the customer pick on the invoice).

  Response of `GET {BASE}/api/v1/merchants` (`200`; credentials are never returned):

  ```json
  {
    "data": [
      {
        "merchant_id": "mer_01m4c8x2n7k5q9w3e6r1t4y8u0",
        "name": "Sepehr",
        "provider": "sepehr",
        "status": "active",
        "is_default": true,
        "configured_credentials": ["terminal_id"],
        "created_at": "2026-10-01T09:12:00Z",
        "updated_at": "2026-10-01T09:12:00Z"
      },
      {
        "merchant_id": "mer_01m4d1a7b3c9d5e2f8g4h6j0k2",
        "name": "ZarinPal",
        "provider": "zarinpal",
        "status": "disabled",
        "is_default": false,
        "configured_credentials": ["merchant_identifier"],
        "created_at": "2026-10-05T11:40:00Z",
        "updated_at": "2026-10-06T08:02:00Z"
      }
    ]
  }
  ```

  Store `merchant_id` (stable) and show `name` (may be renamed). Offer only `"status": "active"`.
* Create a payment on a chosen gateway by adding `"merchant_id": "mer_..."` to the
  `POST {BASE}/api/v1/payments` body. Unknown/disabled → `422 MERCHANT_NOT_FOUND`.
* Send the chosen `"merchant_id": "mer_..."` in the create-payment body (section 3). Without it,
  the client's default merchant is used.
* Webhooks for every gateway arrive at the same URL, signed with the same secret; the payload's
  `merchant_id` / `provider` tell you which gateway was used.
* After a **failed** payment, `new_attempt: true` may carry a different `merchant_id` to retry on
  another gateway (without `merchant_id` the retry uses the default merchant, so send it every
  time). While a payment is still pending it cannot switch gateway; wait for it to fail or expire,
  or create a new payment with a new `order_id` (e.g. `INV-1001-2`).

## 2. Authentication: signing every API request (HMAC-SHA256)

Every request to `{BASE}/api/v1/...` must include four headers:

| Header | Value |
|--------|-------|
| `X-Client-Id` | `TK_CLIENT_ID` |
| `X-Timestamp` | Current Unix time in **seconds**, as a string. Server accepts ±300 s. |
| `X-Nonce` | Random, unique per request, 16-64 chars of `[A-Za-z0-9_-]` (e.g. 32 hex chars) |
| `X-Signature` | Lowercase hex `HMAC_SHA256(key = TK_CLIENT_SECRET, message = canonical)` |

```
canonical = METHOD + "\n"
          + PATH + "\n"
          + X-Timestamp + "\n"
          + X-Nonce + "\n"
          + lowercase_hex(SHA256(raw request body bytes))      # SHA256 of "" for GET / empty body
```

* `METHOD` is uppercase (`GET`, `POST`).
* `PATH` is the **full path including `/payment`**, e.g. `/payment/api/v1/payments`. If there
  is a query string, append `?` and the query string exactly as sent. (Derive it as
  `parse_url(TK_BASE_URL, PATH) + "/api/v1/..."`.)
* Sign the **exact bytes** you send. Serialize the JSON once, sign that string, send that string.
* Also send `Content-Type: application/json` and `Accept: application/json`.
* Use HTTPS, a 30 s timeout, and do not follow redirects.

### Test vectors (your implementation must reproduce these exactly)

Secret: `tksk_0000000000000000000000000000000000000000000000000000000000000000`

**Vector 1 - POST**

* Method `POST`, path `/payment/api/v1/payments`, timestamp `1791446400`,
  nonce `3f2a9c1e5b7d4a608c1e2f3a4b5c6d7e`
* Body (exact bytes):
  `{"order_id":"ORD-10001","amount":500000,"currency":"IRR","description":"Test"}`
* SHA256(body) = `b03bd60e0f4def98abf8f7b868ac947b4ae3a343a97472b820fa23ceaba8a15f`
* Signature = `d57178c4a59ef546d36cc20fb2f290692f273c7194a74a4ee02c538f4ced7187`

**Vector 2 - GET (empty body)**

* Method `GET`, path `/payment/api/v1/payments/pay_01jabcdefghjkmnpqrstvwxyz0`, same timestamp and nonce
* Signature = `c4222b80d8acbc9e984f4d9390498a5ee12dff3a18e5d444e47d6e5150dbe0d5`

## 3. Create a payment (API contract)

`POST {BASE}/api/v1/payments` → full URL `https://tech-kala.com/payment/api/v1/payments`

Optional header: `Idempotency-Key: <8-255 chars [A-Za-z0-9_-:.]>` - recommended, e.g.
`order-<order_id>`.

Request body:

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| `order_id` | string | yes | Your order id. Max 100 chars, `[A-Za-z0-9._:-]`. **Unique per order.** |
| `amount` | integer | yes | **Integer in Rials** for `IRR` (min 10,000). A JSON integer: not `500000.0`, not `"500000"`. |
| `currency` | string | no | `IRR` (Rial, default) or `IRT` (Toman). Use `IRR`. |
| `description` | string | no | Max 500 chars, shown to the customer. |
| `return_url` | string | no | HTTPS. Defaults to the Return URL registered for this site. |
| `merchant_id` | string | no | `mer_...` from `GET /api/v1/merchants` (see "Several gateways"). Omitted → the default gateway. |
| `metadata` | object | no | Max 20 keys / 4 KB, returned as-is. |
| `customer` | object | no (recommended) | `{"mobile": "09121234567", "username": "...", "name": "..."}`: the payer. Lets Tech-Kala's support find payments by mobile / username. Mobile must be an Iranian mobile number (`+98`, `0098` and Persian digits are accepted). |

Response `201 Created` (new) or `200 OK` (same order sent again with identical parameters):

```json
{
  "payment_id": "pay_01m4d50nmey39vdrvjtgvyg7qh",
  "order_id": "ORD-10001",
  "amount": 500000,
  "currency": "IRR",
  "description": "Test",
  "status": "pending",
  "payment_url": "https://tech-kala.com/payment/pay/pay_01m4d50nmey39vdrvjtgvyg7qh",
  "merchant_id": "mer_...",
  "provider": "sepehr",
  "reference_number": null,
  "trace_number": null,
  "card_mask": null,
  "return_url": "https://<this-site>/payment/result",
  "metadata": null,
  "attempts": 1,
  "created_at": "2026-10-08T07:00:35Z",
  "updated_at": "2026-10-08T07:00:35Z",
  "paid_at": null,
  "expires_at": "2026-10-08T08:00:35Z"
}
```


## 3a. Invoice → payment mapping

Create a table (or reuse the panel's transaction table) to link invoices and Tech-Kala payments:

| Column | Notes |
|--------|-------|
| `invoice_id` | Panel invoice |
| `tk_order_id` | The `order_id` sent to Tech-Kala (unique) |
| `tk_payment_id` | `payment_id` returned by Tech-Kala (`pay_...`) |
| `amount`, `currency` | Exactly what was sent |
| `status` | Last known Tech-Kala status |
| `reference_number`, `card_mask`, `paid_at` | From the webhook / status response |
| timestamps | |

**`order_id`:** Tech-Kala allows **one payment per `order_id`** (per panel). Use
`INV-{invoiceId}-{n}` where `n` starts at 1 and increases only when a fresh payment is needed
(see 3b). Characters allowed: `[A-Za-z0-9._:-]`, max 100.

**Amount:** must be a JSON **integer**.

* Panel stores **Rials** → send `"amount": total, "currency": "IRR"`.
* Panel stores **Tomans** → send `"amount": total, "currency": "IRT"` (Tech-Kala converts exactly
  for the bank). Do not multiply or divide yourself unless you also change `currency`.
* Minimums: 10,000 IRR / 1,000 IRT. Never send floats or strings.
* Send the **amount still due** on the invoice (total minus any credits already applied).

**Description:** e.g. `"Invoice #123 - <panel name>"` (max 500 chars).

**Return URL:** send `return_url` = the panel's return endpoint for this invoice, e.g.
`https://<panel-domain>/payments/techkala/return?invoice=123` (HTTPS).

**Idempotency-Key:** `tk-{tk_order_id}` (one key per `order_id`).

## 3b. When the customer clicks "Pay online"

1. If the invoice is already paid → show it as paid, do nothing.
2. If there is an existing mapping row for this invoice:
   * status `pending` or `redirected` and not expired → reuse it: redirect to its `payment_url`
     (fetch it with `GET {BASE}/api/v1/payments/{id}` if not stored).
   * status `failed` → retry the same payment: `POST {BASE}/api/v1/payments` with the **same**
     `order_id`, `amount`, `currency` and `"new_attempt": true`, with a **new** Idempotency-Key
     (e.g. `tk-{tk_order_id}-retry-{k}`) or none. Reusing the original key returns the old failed
     payment without a new attempt.
   * status `expired` or `cancelled` → create a **new** row with `n + 1` (new `order_id`).
   * the invoice amount changed while the old payment is still `pending`/`redirected` → first
     `POST {BASE}/api/v1/payments/{old_id}/cancel` (so the old amount can no longer be paid),
     then create a new row with `n + 1`.
   * status `callback_received` or `verifying` → payment is being confirmed; show "processing",
     do not start another payment.
3. Otherwise create a new row with `n = 1` and call `POST {BASE}/api/v1/payments`.
4. Store `tk_payment_id` and `status`, then redirect the browser (302/303) to `payment_url`.
5. If the response `status` is `failed` (bank unreachable/rejected) show an error with a
   "try again" button.
6. Prevent double clicks: only one in-flight create per invoice (lock or unique constraint).

## 4. Return endpoint (customer comes back)

Tech-Kala redirects the browser (HTTP 303, GET) to the `return_url` with added parameters:

```
https://<panel-domain>/payments/techkala/return?invoice=123&payment_id=pay_...&order_id=INV-123-1&status=paid
```

**These parameters are NOT proof of payment.** On this endpoint:

1. Find the mapping row by `order_id` and check `payment_id` matches.
2. Call `GET {BASE}/api/v1/payments/{payment_id}` (signed) and use **its** `status`.
3. `paid` → mark the invoice paid (section 6), then redirect to the invoice page with a success
   message. `failed`/`cancelled`/`expired` → invoice page with an error and the "Pay online"
   button. `callback_received`/`verifying` → "Payment is being confirmed", optionally call
   `POST {BASE}/api/v1/payments/{payment_id}/verify` once; the webhook will finish it.

## 5. Webhook (source of truth)

Tech-Kala sends `POST` to the Webhook URL with a JSON body and headers:

| Header | Meaning |
|--------|---------|
| `X-Webhook-Event` | e.g. `payment.succeeded` |
| `X-Webhook-Delivery-Id` | Unique id of this delivery (use for de-duplication) |
| `X-Webhook-Timestamp` | Unix seconds |
| `X-Webhook-Signature` | Lowercase hex `HMAC_SHA256(key = TK_WEBHOOK_SECRET, message = timestamp + "." + raw_body)` |

Body:

```json
{
  "event": "payment.succeeded",
  "payment_id": "pay_...",
  "order_id": "INV-123-1",
  "amount": 500000,
  "currency": "IRR",
  "status": "paid",
  "reference_number": "130085019108",
  "card_mask": "621986******4557",
  "paid_at": "2026-10-08T07:03:25Z",
  "occurred_at": "2026-10-08T07:03:26Z"
}
```

Events: `payment.created`, `payment.pending`, `payment.succeeded`, `payment.failed`,
`payment.expired`, `payment.cancelled`. Only **`payment.succeeded`** means "deliver the goods".

Handler requirements:

1. Read the **raw** request body before any JSON parsing (signature is over the raw bytes).
2. Reject with `401` unless `|now - X-Webhook-Timestamp| <= 300` **and**
   `constant_time_equals(expected, X-Webhook-Signature)`.
3. De-duplicate on `X-Webhook-Delivery-Id` (store processed ids). Repeats are normal.
4. Find the mapping row by `order_id` (section 3a); check `payment_id`, `amount` and `currency`
   match. If they do not match, log and do not mark paid.
5. For `payment.succeeded` mark the invoice paid (section 6). For `payment.failed` /
   `payment.expired` / `payment.cancelled` update the mapping row status only; the invoice stays
   unpaid (never downgrade a paid invoice).
6. Respond `200` quickly (within 10 s). Do slow work asynchronously. Non-2xx responses are
   retried with exponential backoff (30 s, 60 s, 120 s, …, up to 8 attempts).
7. The endpoint must be exempt from CSRF protection and must not require a login.

**Webhook test vector:** secret `whsec_1111111111111111111111111111111111111111111111111111111111111111`,
timestamp `1791446606`, raw body:

```
{"event":"payment.succeeded","payment_id":"pay_01jabcdefghjkmnpqrstvwxyz0","order_id":"ORD-10001","amount":500000,"currency":"IRR","status":"paid","reference_number":"130085019108","card_mask":"621986******4557","paid_at":"2026-10-08T07:03:25Z","occurred_at":"2026-10-08T07:03:26Z"}
```

Expected signature: `50e419609abdcedb3de7f4f9bb5a0802916519fc5c4e43075da60b96686b0354`

## 6. Marking the invoice paid (idempotency)

* Do it in **one place** used by both the webhook and the return endpoint.
* Must be idempotent and concurrency-safe (webhook and return page can arrive together): use a
  DB transaction with a row lock, or a conditional update
  (`UPDATE invoices SET status='paid' ... WHERE id=? AND status<>'paid'`).
* Record the payment in the panel's normal way (transaction/ledger entry with gateway name
  "Tech-Kala", `reference_number` as the transaction id, amount, date), so accounting and
  reports work as with other payment methods. Record it **once**.
* Check before marking paid: `amount` and `currency` from Tech-Kala equal the mapping row; if not,
  do not mark paid, log and alert an administrator.
* A paid invoice never becomes unpaid because of a later `failed`/`expired` event.
* Trigger the panel's usual "invoice paid" side effects (emails, service activation, etc.).

## 7. Other endpoints

All signed exactly like section 2.

| Method & path | Purpose |
|---------------|---------|
| `GET {BASE}/api/v1/payments/{payment_id}` | Authoritative status. `404 PAYMENT_NOT_FOUND` for unknown ids. |
| `POST {BASE}/api/v1/payments/{payment_id}/verify` | Ask Tech-Kala to re-verify with the bank (safe to repeat). |
| `POST {BASE}/api/v1/payments/{payment_id}/cancel` | Cancel an unpaid payment (`created`/`pending`/`redirected`). |
| `POST {BASE}/api/v1/payments` with the **same** `order_id`, `amount`, `currency` and `"new_attempt": true` | Retry a payment whose status is `failed` (new bank attempt, same `payment_id`). Send a **new** `Idempotency-Key` (e.g. `order-ORD-10001-retry-2`) or none: reusing the original key returns the original failed payment without a new attempt. |

To start a completely fresh payment for an order whose payment `expired` or was `cancelled`,
use a new `order_id` (e.g. `INV-123-2`).

Payment statuses: `created`, `pending`, `redirected` (customer at the bank),
`callback_received`, `verifying` (confirmation in progress), `paid`, `failed`, `cancelled`,
`expired`. Final: `paid`, `failed`, `cancelled`, `expired`. Unpaid payments expire after 60 min.

## 8. Errors

Every error has this shape (and an `X-Request-Id` header):

```json
{ "error": { "code": "MERCHANT_NOT_FOUND", "message": "...", "request_id": "req_01..." } }
```

Log `code` and `request_id` (never the secrets). Important codes:

| HTTP | Code | Meaning / action |
|------|------|------------------|
| 401 | `AUTH_INVALID_SIGNATURE` | Wrong secret/key/path/body bytes. Check lengths (30/69) and that PATH includes `/payment`. |
| 401 | `AUTH_TIMESTAMP_EXPIRED` | Server clock is off by > 300 s. Fix NTP. |
| 401 | `AUTH_NONCE_REPLAYED` | Nonce reused. Generate a new one per request (also on retries). |
| 401 | `AUTH_REQUIRED` | Missing auth headers. |
| 403 | `AUTH_CLIENT_DISABLED` | Credential revoked / site disabled. Contact Tech-Kala. |
| 409 | `ORDER_ALREADY_EXISTS` | Same `order_id` with different amount/params. |
| 409 | `NEW_ATTEMPT_NOT_ALLOWED` | `new_attempt` only works for `failed` payments. |
| 422 | `IDEMPOTENCY_KEY_REUSED` | Same `Idempotency-Key` with a different body. Use one key per distinct request. |
| 422 | `VALIDATION_ERROR` | See `error.details` per field. |
| 422 | `MERCHANT_NOT_FOUND` / `PROVIDER_UNAVAILABLE` | Gateway not configured on Tech-Kala's side. Contact Tech-Kala. |
| 429 | `RATE_LIMITED` | Back off and retry. |
| 5xx | `INTERNAL_ERROR` | Retry later with the **same** `order_id` (safe: idempotent). |


## 9. Acceptance criteria (definition of done)

1. Unit tests reproduce **all three test vectors** (two request signatures in section 2 and the
   webhook signature in section 5).
2. Settings screen stores secrets encrypted, validates lengths (30 / 69), shows the Webhook URL,
   and "Test connection" returns success with real keys.
3. "Pay online" on an unpaid invoice creates (or reuses) a Tech-Kala payment and redirects to
   `payment_url`; double clicks do not create two payments.
4. The return endpoint confirms via `GET /api/v1/payments/{id}` and never trusts query
   parameters.
5. The webhook verifies timestamp + signature (constant-time), rejects invalid requests with 401,
   de-duplicates by `X-Webhook-Delivery-Id`, checks amount/currency, and marks the invoice paid
   exactly once with a proper transaction record.
6. Failed payments can be retried; expired/cancelled ones start a new `order_id`.
7. Secrets never appear in logs, HTML, JS, error messages or the repository.
8. One real low-value invoice is paid end to end, and Tech-Kala's admin panel (**Webhooks**)
   shows the `payment.succeeded` delivery as **delivered**.

## 10. Customer-facing notes

* Tell customers to **turn off VPN** before paying (Iranian bank pages reject foreign IPs).
* If the customer closes the bank page, the payment expires after 60 minutes; the invoice stays
  unpaid and can be paid again.

## 11. Reference implementation snippets (PHP)

```php
function tk_request(string $method, string $apiPath, ?array $payload, array $extraHeaders = []): array
{
    $base   = rtrim(getenv('TK_BASE_URL'), '/');                       // https://tech-kala.com/payment
    $path   = parse_url($base, PHP_URL_PATH).$apiPath;                  // /payment/api/v1/...
    $origin = substr($base, 0, strlen($base) - strlen((string) parse_url($base, PHP_URL_PATH)));
    $body   = $payload === null ? '' : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $ts     = (string) time();
    $nonce  = bin2hex(random_bytes(16));
    $canonical = implode("\n", [strtoupper($method), $path, $ts, $nonce, hash('sha256', $body)]);

    $ch = curl_init($origin.$path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER     => array_merge([
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Client-Id: '.getenv('TK_CLIENT_ID'),
            'X-Timestamp: '.$ts,
            'X-Nonce: '.$nonce,
            'X-Signature: '.hash_hmac('sha256', $canonical, getenv('TK_CLIENT_SECRET')),
        ], $extraHeaders),
        CURLOPT_POSTFIELDS     => $body === '' ? null : $body,
    ]);
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    return [$status, json_decode((string) $raw, true)];
}

// Create: [$s, $p] = tk_request('POST', '/api/v1/payments', ['order_id' => 'ORD-10001', 'amount' => 500000, 'currency' => 'IRR'], ['Idempotency-Key: order-ORD-10001']);
// Status: [$s, $p] = tk_request('GET', '/api/v1/payments/'.$paymentId, null);

function tk_webhook_is_valid(string $rawBody, string $timestamp, string $signature): bool
{
    if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300) {
        return false;
    }
    $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, getenv('TK_WEBHOOK_SECRET'));

    return hash_equals($expected, strtolower($signature));
}
// $raw = file_get_contents('php://input');
// tk_webhook_is_valid($raw, $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? '', $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '')
```

Node.js and full details: Tech-Kala's `docs/INTEGRATION.md` and `docs/openapi.yaml` (ask the
site owner for these files if needed).
