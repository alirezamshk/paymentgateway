# Client integration guide

How an external site ("Client") takes payments through the Tech-Kala payment service.
Full API reference: [`openapi.yaml`](openapi.yaml).

## 1. What you receive from Tech-Kala

| Item | Example | Used for |
|------|---------|----------|
| Client key id | `tkc_01j...` | `X-Client-Id` header |
| Client secret | `tksk_...` | Signing API requests. Shown once. Keep it server-side. |
| Webhook secret | `whsec_...` | Verifying webhooks from Tech-Kala. Shown once. |

Tech-Kala (or you, via `POST /api/v1/merchants`) configures one or more **merchants** for your
site, each linked to a PSP (ZarinPal, Sepehr, AsanPardakht, Sepordeh). One merchant is your
default. You never send PSP credentials when creating a payment, and you never talk to the PSP
directly.

## 2. Flow

```
Your server ──POST /api/v1/payments──▶ Tech-Kala ──▶ PSP (token)
Your server ◀── payment_url ──────────┘
Customer ──▶ https://tech-kala.com/pay/{payment_id} ──▶ PSP payment page
PSP ──▶ Tech-Kala callback ──▶ PSP verify ──▶ payment paid/failed
Tech-Kala ──signed webhook──▶ your webhook URL
Customer ◀── 303 redirect to your return_url?payment_id=..&order_id=..&status=..
```

The `status` in the return redirect is only a hint. Before delivering goods, confirm through
the signed webhook or `GET /api/v1/payments/{payment_id}`.

The service may be installed in a sub-directory, e.g. `https://tech-kala.com/payment`. Then
every API path starts with that folder (`/payment/api/v1/payments`), and you sign that full
path. Use the base URL you were given exactly.

Customers must turn off VPNs before paying: Shaparak payment pages reject foreign IPs.

A dependency-free reference client you can run from the command line is in
[`scripts/test-client.php`](../scripts/test-client.php).

## 3. Signing requests

```
canonical = METHOD \n PATH \n TIMESTAMP \n NONCE \n hex(sha256(BODY))
X-Signature = hex(hmac_sha256(client_secret, canonical))
```

* `PATH` is the full path with a leading slash, e.g. `/api/v1/payments`. If the service is
  installed in a sub-directory, include it, e.g. `/payment/api/v1/payments`. If there is a query
  string, append `?` and the query string exactly as sent.
* `TIMESTAMP` is Unix seconds. The server accepts ±300 seconds, so keep your clock in sync (NTP).
* `NONCE` must be unique for every request (16-64 chars `[A-Za-z0-9_-]`).
* The key id is 30 characters (`tkc_...`) and the secret 69 characters (`tksk_...`). Most
  `AUTH_INVALID_SIGNATURE` errors are copy/paste mistakes; check the lengths first.
* Sign the exact bytes you send as the body. For a `GET` request the body is empty.

### PHP

```php
function techkala_request(string $method, string $path, ?array $payload, string $keyId, string $secret, array $extraHeaders = []): array
{
    $body = $payload === null ? '' : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $timestamp = (string) time();
    $nonce = bin2hex(random_bytes(16));
    $canonical = implode("\n", [strtoupper($method), $path, $timestamp, $nonce, hash('sha256', $body)]);

    $headers = array_merge([
        'Content-Type: application/json',
        'Accept: application/json',
        'X-Client-Id: '.$keyId,
        'X-Timestamp: '.$timestamp,
        'X-Nonce: '.$nonce,
        'X-Signature: '.hash_hmac('sha256', $canonical, $secret),
    ], $extraHeaders);

    $ch = curl_init('https://tech-kala.com'.$path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $body === '' ? null : $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    return [$status, json_decode((string) $response, true)];
}

// Create a payment
[$status, $payment] = techkala_request('POST', '/api/v1/payments', [
    'order_id' => 'ORD-10001',
    'amount' => 500000,          // integer, Rials for IRR
    'currency' => 'IRR',
    'description' => 'Service purchase',
    'return_url' => 'https://panel-a.example.com/payment/result',
], getenv('TK_KEY_ID'), getenv('TK_SECRET'), ['Idempotency-Key: order-ORD-10001']);

if (in_array($status, [200, 201], true) && $payment['payment_url']) {
    header('Location: '.$payment['payment_url'], true, 303);
}
```

### Node.js

```js
import crypto from 'node:crypto';

async function techkala(method, path, payload, keyId, secret) {
  const body = payload ? JSON.stringify(payload) : '';
  const timestamp = Math.floor(Date.now() / 1000).toString();
  const nonce = crypto.randomBytes(16).toString('hex');
  const canonical = [method.toUpperCase(), path, timestamp, nonce,
    crypto.createHash('sha256').update(body).digest('hex')].join('\n');
  const signature = crypto.createHmac('sha256', secret).update(canonical).digest('hex');

  const res = await fetch(`https://tech-kala.com${path}`, {
    method,
    headers: {
      'Content-Type': 'application/json', Accept: 'application/json',
      'X-Client-Id': keyId, 'X-Timestamp': timestamp, 'X-Nonce': nonce, 'X-Signature': signature,
    },
    body: body || undefined,
  });
  return [res.status, await res.json()];
}
```

## 4. Receiving webhooks

Headers: `X-Webhook-Event`, `X-Webhook-Delivery-Id`, `X-Webhook-Timestamp`, `X-Webhook-Signature`.
The `X-Webhook-` prefix is the default. Your payment service operator may configure a different
prefix (`WEBHOOK_HEADER_PREFIX`); the header names are otherwise identical.

```php
$raw = file_get_contents('php://input');
$timestamp = (int) ($_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? 0);
$signature = (string) ($_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '');
$expected = hash_hmac('sha256', $timestamp.'.'.$raw, getenv('TK_WEBHOOK_SECRET'));

if (abs(time() - $timestamp) > 300 || ! hash_equals($expected, $signature)) {
    http_response_code(401);
    exit;
}

$event = json_decode($raw, true);
$deliveryId = $_SERVER['HTTP_X_WEBHOOK_DELIVERY_ID'];
// 1. Ignore if $deliveryId was already processed (deliveries can repeat).
// 2. Look up your order by $event['order_id'] and check amount + currency match.
// 3. On "payment.succeeded" mark the order paid (idempotently).
http_response_code(200);
```

Return any `2xx` status quickly. Do slow work asynchronously. Failed deliveries are retried
with exponential backoff, up to 8 attempts by default.

| Event | Meaning |
|-------|---------|
| `payment.created` | Payment record created |
| `payment.pending` | PSP token obtained; customer can pay |
| `payment.succeeded` | PSP verified the payment. **Deliver the goods.** |
| `payment.failed` | PSP rejected the payment, or verification failed |
| `payment.expired` | Not paid within the TTL (default 60 min) |
| `payment.cancelled` | Cancelled via the API |

## 5. Retrying a failed payment

Send the same `order_id`, `amount` and `currency` with `"new_attempt": true`. This starts a new
PSP attempt on the same payment. It is only allowed while the payment is `failed`. A plain
repeat of the create request without the flag returns the existing payment unchanged.

## 6. Money

`amount` is an integer in the unit of `currency`: `IRR` is in Rials, `IRT` is in Tomans.
The service never silently converts. If a PSP needs another unit and the amount cannot be
converted exactly (e.g. 500,005 IRR to Tomans), the attempt fails with `AMOUNT_NOT_CONVERTIBLE`.
