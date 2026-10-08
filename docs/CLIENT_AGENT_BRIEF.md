# Task brief: integrate this website with the Tech-Kala Payment Service

> Give this whole document to the developer or coding agent that will implement payments on a
> client website. It is self-contained. Credentials are **not** in this document - they are
> provided separately as environment variables (section 1).

## 0. Goal

Implement online payments on this website using the **Tech-Kala Payment Service** (a central
payment gateway). The website never talks to a bank/PSP directly. The flow is:

```
Customer clicks "Pay"
  → our server calls  POST {BASE}/api/v1/payments            (signed with HMAC)
  → redirect the customer to the returned payment_url          (Tech-Kala page → bank)
  → customer pays at the bank
  → Tech-Kala verifies with the bank, then:
       a) sends a signed webhook to our WEBHOOK URL           (source of truth)
       b) redirects the customer to our RETURN URL            (display only, NOT proof)
  → our server marks the order paid (only after a) or after GET {BASE}/api/v1/payments/{id} says "paid")
```

**Before writing code, inspect this website's stack** (language, framework, ORM, how orders are
stored, how config/secrets are loaded) and implement the integration in the project's existing
style. Do not add new infrastructure unless necessary.

## 1. Configuration (environment variables)

Read these from the environment / the framework's secret config. **Never hard-code them, never
commit them, never log them, never send them to the browser.**

| Variable | Value | Notes |
|----------|-------|-------|
| `TK_BASE_URL` | `https://tech-kala.com/payment` | Includes the `/payment` folder. No trailing slash. |
| `TK_CLIENT_ID` | `tkc_...` | **30 characters**. Sent as `X-Client-Id`. |
| `TK_CLIENT_SECRET` | `tksk_...` | **69 characters**. Signs API requests. Server-side only. |
| `TK_WEBHOOK_SECRET` | `whsec_...` | Verifies webhooks. Server-side only. |

On startup (or in a health check) validate the lengths of `TK_CLIENT_ID` (30) and
`TK_CLIENT_SECRET` (69): copy/paste mistakes are the most common cause of signature errors.

The site owner registers these two URLs with Tech-Kala (you implement them):

* **Webhook URL** - e.g. `https://<this-site>/payment/tk-webhook` (POST, HTTPS, public, CSRF-exempt)
* **Return URL** - e.g. `https://<this-site>/payment/result` (GET, HTTPS)

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

## 3. Create a payment

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
| `metadata` | object | no | Max 20 keys / 4 KB, returned as-is. |

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

Then:

1. **Store `payment_id` on the order** (and the status).
2. If `status` is `pending` (or `redirected`) and `payment_url` is not null → **redirect the
   customer's browser to `payment_url`** (HTTP 302/303).
3. If `status` is `failed` → the bank could not be reached / rejected the request; show an error
   and let the customer retry (section 7).

## 4. Return URL (customer comes back)

Tech-Kala redirects the browser (HTTP 303, GET) to:

```
{return_url}?payment_id=pay_...&order_id=ORD-10001&status=paid
```

**These query parameters are NOT proof of payment** (anyone can type them). On this page:

1. Look up the order by `order_id` and check `payment_id` matches the stored one.
2. Call `GET {BASE}/api/v1/payments/{payment_id}` (signed) and use **its** `status`.
3. If `paid` → mark the order paid (idempotently, see section 6) and show success.
   Otherwise show the appropriate message ("payment failed / cancelled / still processing").
4. If the status is `callback_received` or `verifying`, the bank confirmation is still in
   progress: show "processing", optionally call `POST {BASE}/api/v1/payments/{payment_id}/verify`
   once, and rely on the webhook.

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
  "order_id": "ORD-10001",
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
4. Find the order by `order_id`; check `payment_id`, `amount` and `currency` match the order.
   If they do not match, log and do not mark paid.
5. For `payment.succeeded` mark the order paid (idempotently). For `payment.failed` /
   `payment.expired` / `payment.cancelled` mark accordingly only if the order is not already paid.
6. Respond `200` quickly (within 10 s). Do slow work asynchronously. Non-2xx responses are
   retried with exponential backoff (30 s, 60 s, 120 s, …, up to 8 attempts).
7. The endpoint must be exempt from CSRF protection and must not require a login.

**Webhook test vector:** secret `whsec_1111111111111111111111111111111111111111111111111111111111111111`,
timestamp `1791446606`, raw body:

```
{"event":"payment.succeeded","payment_id":"pay_01jabcdefghjkmnpqrstvwxyz0","order_id":"ORD-10001","amount":500000,"currency":"IRR","status":"paid","reference_number":"130085019108","card_mask":"621986******4557","paid_at":"2026-10-08T07:03:25Z","occurred_at":"2026-10-08T07:03:26Z"}
```

Expected signature: `50e419609abdcedb3de7f4f9bb5a0802916519fc5c4e43075da60b96686b0354`

## 6. Order state rules (idempotency)

* "Mark paid" must be idempotent and safe under concurrency: the webhook and the return page
  can arrive at the same time. Use a DB transaction / row lock or a conditional update
  (`UPDATE orders SET status='paid' WHERE id=? AND status<>'paid'`) so goods are delivered once.
* A paid order never goes back to unpaid.
* Store at least: `tk_payment_id`, payment status, `reference_number`, `paid_at`.

## 7. Other endpoints

All signed exactly like section 2.

| Method & path | Purpose |
|---------------|---------|
| `GET {BASE}/api/v1/payments/{payment_id}` | Authoritative status. `404 PAYMENT_NOT_FOUND` for unknown ids. |
| `POST {BASE}/api/v1/payments/{payment_id}/verify` | Ask Tech-Kala to re-verify with the bank (safe to repeat). |
| `POST {BASE}/api/v1/payments/{payment_id}/cancel` | Cancel an unpaid payment (`created`/`pending`/`redirected`). |
| `POST {BASE}/api/v1/payments` with the **same** `order_id`, `amount`, `currency` and `"new_attempt": true` | Retry a payment whose status is `failed` (new bank attempt, same `payment_id`). Send a **new** `Idempotency-Key` (e.g. `order-ORD-10001-retry-2`) or none: reusing the original key returns the original failed payment without a new attempt. |

To start a completely fresh payment for an order whose payment `expired` or was `cancelled`,
use a new `order_id` (e.g. `ORD-10001-2`).

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

## 9. Customer-facing notes

* Show amounts in Toman or Rial clearly; the API amount is **Rials**.
* Tell customers to **turn off VPN** before paying (Iranian bank pages reject foreign IPs).
* If the customer closes the bank page, the payment expires automatically; they can pay again.

## 10. Acceptance criteria (definition of done)

1. Unit tests reproduce **all three test vectors** above (vector 1, vector 2, webhook).
2. Secrets come only from environment/secret config; not in code, logs, HTML or JS.
3. Creating a payment stores `payment_id` on the order and redirects to `payment_url`.
4. The return page confirms via `GET /api/v1/payments/{id}` and never trusts query parameters.
5. The webhook endpoint verifies timestamp + signature (constant-time), rejects invalid ones with
   401, de-duplicates by delivery id, checks amount/currency/order, and is idempotent.
6. Paying the same order twice is impossible; duplicate webhooks do not deliver goods twice.
7. One real low-value payment succeeds end to end, and Tech-Kala's admin panel
   (**Webhooks**) shows the `payment.succeeded` delivery as **delivered**.

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
