<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Security\RequestSigner;
use App\Webhooks\WebhookHeaders;
use App\Webhooks\WebhookSigner;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Developer documentation for client integrations, rendered for this installation: the base URL,
 * the sub-directory that must be signed, the webhook header prefix and the test vectors all come
 * from the running configuration. Contains no secrets.
 */
class DocsController extends Controller
{
    private const VECTOR_SECRET = 'tksk_0000000000000000000000000000000000000000000000000000000000000000';

    private const VECTOR_WEBHOOK_SECRET = 'whsec_1111111111111111111111111111111111111111111111111111111111111111';

    private const VECTOR_TIMESTAMP = '1791446400';

    private const VECTOR_NONCE = '3f2a9c1e5b7d4a608c1e2f3a4b5c6d7e';

    public function show(Request $request): View
    {
        $this->authorizeView($request);

        $base = rtrim(url('/'), '/');
        $basePath = rtrim((string) parse_url($base, PHP_URL_PATH), '/');

        return view('docs.index', [
            'base' => $base,
            'basePath' => $basePath,
            'headers' => [
                'event' => WebhookHeaders::event(),
                'delivery' => WebhookHeaders::deliveryId(),
                'timestamp' => WebhookHeaders::timestamp(),
                'signature' => WebhookHeaders::signature(),
            ],
            'ttl' => (int) config('payments.payment_ttl_minutes'),
            'minIrr' => (int) config('payments.amount_limits.IRR.min'),
            'vectors' => $this->vectors($basePath),
            'samples' => $this->samples($base),
            'responseExample' => $this->responseExample($base),
        ]);
    }

    /** OpenAPI file with this installation's server URL. */
    public function openapi(Request $request): Response
    {
        $this->authorizeView($request);

        $yaml = (string) file_get_contents(base_path('docs/openapi.yaml'));
        $yaml = preg_replace('/^(servers:\n  - url: ).*$/m', '${1}'.rtrim(url('/'), '/'), $yaml, 1);

        return response($yaml, 200, ['Content-Type' => 'application/yaml; charset=utf-8']);
    }

    private function authorizeView(Request $request): void
    {
        abort_unless(config('payments.public_docs') || $request->user()?->is_admin, 404);
    }

    private function vectors(string $basePath): array
    {
        $body = '{"order_id":"ORD-10001","amount":500000,"currency":"IRR","description":"Test"}';
        $postPath = $basePath.'/api/v1/payments';
        $getPath = $basePath.'/api/v1/payments/pay_01jabcdefghjkmnpqrstvwxyz0';
        $webhookBody = '{"event":"payment.succeeded","payment_id":"pay_01jabcdefghjkmnpqrstvwxyz0","order_id":"ORD-10001","amount":500000,"currency":"IRR","status":"paid","reference_number":"130085019108","card_mask":"621986******4557","paid_at":"2026-10-08T07:03:25Z","occurred_at":"2026-10-08T07:03:26Z"}';
        $webhookTs = 1791446606;

        return [
            'secret' => self::VECTOR_SECRET,
            'timestamp' => self::VECTOR_TIMESTAMP,
            'nonce' => self::VECTOR_NONCE,
            'post_path' => $postPath,
            'post_body' => $body,
            'post_body_hash' => hash('sha256', $body),
            'post_canonical' => RequestSigner::canonical('POST', $postPath, self::VECTOR_TIMESTAMP, self::VECTOR_NONCE, $body),
            'post_signature' => RequestSigner::sign(self::VECTOR_SECRET, 'POST', $postPath, self::VECTOR_TIMESTAMP, self::VECTOR_NONCE, $body),
            'get_path' => $getPath,
            'get_signature' => RequestSigner::sign(self::VECTOR_SECRET, 'GET', $getPath, self::VECTOR_TIMESTAMP, self::VECTOR_NONCE, ''),
            'webhook_secret' => self::VECTOR_WEBHOOK_SECRET,
            'webhook_timestamp' => $webhookTs,
            'webhook_body' => $webhookBody,
            'webhook_signature' => WebhookSigner::sign(self::VECTOR_WEBHOOK_SECRET, $webhookTs, $webhookBody),
        ];
    }

    private function responseExample(string $base): string
    {
        return json_encode([
            'payment_id' => 'pay_01m4d50nmey39vdrvjtgvyg7qh',
            'order_id' => 'ORD-10001',
            'amount' => 500000,
            'currency' => 'IRR',
            'description' => 'Order 10001',
            'status' => 'pending',
            'payment_url' => $base.'/pay/pay_01m4d50nmey39vdrvjtgvyg7qh',
            'merchant_id' => 'mer_01m4c8x2n7k5q9w3e6r1t4y8u0',
            'provider' => 'sepehr',
            'reference_number' => null,
            'trace_number' => null,
            'card_mask' => null,
            'customer' => ['mobile' => '09121234567', 'username' => 'ali_m', 'name' => null],
            'return_url' => 'https://shop.example.com/payment/result',
            'metadata' => ['invoice' => 1001],
            'attempts' => 1,
            'created_at' => '2026-10-08T07:00:35Z',
            'updated_at' => '2026-10-08T07:00:35Z',
            'paid_at' => null,
            'expires_at' => '2026-10-08T08:00:35Z',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @return array<string, string> */
    private function samples(string $base): array
    {
        $php = <<<'PHP'
<?php
// Keep these server-side (environment / secret store). Never send them to a browser.
const TK_BASE = '__BASE__';
$keyId = getenv('TK_CLIENT_ID');        // tkc_... (30 chars)
$secret = getenv('TK_CLIENT_SECRET');   // tksk_... (69 chars)

function tk_request(string $method, string $path, ?array $payload, string $keyId, string $secret, array $extra = []): array
{
    $body = $payload === null ? '' : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $fullPath = rtrim((string) parse_url(TK_BASE, PHP_URL_PATH), '/').$path;   // sign the full path
    $timestamp = (string) time();
    $nonce = bin2hex(random_bytes(16));
    $canonical = implode("\n", [strtoupper($method), $fullPath, $timestamp, $nonce, hash('sha256', $body)]);

    $ch = curl_init(TK_BASE.$path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => array_merge([
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Client-Id: '.$keyId,
            'X-Timestamp: '.$timestamp,
            'X-Nonce: '.$nonce,
            'X-Signature: '.hash_hmac('sha256', $canonical, $secret),
        ], $extra),
        CURLOPT_POSTFIELDS => $body === '' ? null : $body,   // the exact bytes that were signed
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 30,
    ]);
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    return [$status, json_decode((string) $raw, true)];
}

// 1) Create a payment and send the customer to payment_url
[$status, $payment] = tk_request('POST', '/api/v1/payments', [
    'order_id' => 'ORD-10001',
    'amount' => 500000,               // integer, Rials
    'currency' => 'IRR',
    'description' => 'Order 10001',
    'return_url' => 'https://shop.example.com/payment/result',
    'customer' => ['mobile' => '09121234567', 'username' => 'ali_m'],
], $keyId, $secret, ['Idempotency-Key: order-ORD-10001']);

if (in_array($status, [200, 201], true) && ! empty($payment['payment_url'])) {
    header('Location: '.$payment['payment_url'], true, 303);
    exit;
}
error_log('Payment error: '.($payment['error']['code'] ?? $status).' '.($payment['error']['request_id'] ?? ''));

// 2) On the return page: never trust the query string, ask the API
[$status, $payment] = tk_request('GET', '/api/v1/payments/'.rawurlencode($_GET['payment_id'] ?? ''), null, $keyId, $secret);
$paid = $status === 200 && $payment['status'] === 'paid';
PHP;

        $webhook = <<<'PHP'
<?php
// Webhook endpoint (POST, no login, CSRF-exempt)
$raw = file_get_contents('php://input');                       // raw bytes, before json_decode
$timestamp = (int) ($_SERVER['HTTP___TS__'] ?? 0);
$signature = (string) ($_SERVER['HTTP___SIG__'] ?? '');
$expected = hash_hmac('sha256', $timestamp.'.'.$raw, getenv('TK_WEBHOOK_SECRET'));

if (abs(time() - $timestamp) > 300 || ! hash_equals($expected, $signature)) {
    http_response_code(401);
    exit;
}

$event = json_decode($raw, true);
$deliveryId = $_SERVER['HTTP___DID__'] ?? '';
// 1. Skip if $deliveryId was already processed (deliveries can repeat).
// 2. Find your order by $event['order_id']; check payment_id, amount and currency.
// 3. "payment.succeeded" -> mark the order paid, once (row lock / conditional UPDATE).
http_response_code(200);
PHP;

        $node = <<<'JS'
import crypto from 'node:crypto';

const BASE = '__BASE__';
const KEY_ID = process.env.TK_CLIENT_ID;
const SECRET = process.env.TK_CLIENT_SECRET;

export async function tkRequest(method, path, payload, extraHeaders = {}) {
  const body = payload ? JSON.stringify(payload) : '';
  const fullPath = new URL(BASE).pathname.replace(/\/$/, '') + path; // sign the full path
  const timestamp = Math.floor(Date.now() / 1000).toString();
  const nonce = crypto.randomBytes(16).toString('hex');
  const canonical = [method.toUpperCase(), fullPath, timestamp, nonce,
    crypto.createHash('sha256').update(body).digest('hex')].join('\n');

  const res = await fetch(BASE + path, {
    method,
    redirect: 'manual',
    headers: {
      'Content-Type': 'application/json', Accept: 'application/json',
      'X-Client-Id': KEY_ID, 'X-Timestamp': timestamp, 'X-Nonce': nonce,
      'X-Signature': crypto.createHmac('sha256', SECRET).update(canonical).digest('hex'),
      ...extraHeaders,
    },
    body: body || undefined,
  });
  return [res.status, await res.json()];
}

// Webhook (Express): use the raw body, e.g. app.post('/tk/webhook', express.raw({ type: '*/*' }), handler)
export function verifyWebhook(req) {
  const ts = Number(req.get('__TS__'));
  const expected = crypto.createHmac('sha256', process.env.TK_WEBHOOK_SECRET)
    .update(`${ts}.${req.body.toString('utf8')}`).digest('hex');
  const given = Buffer.from(req.get('__SIG__') || '');
  return Math.abs(Date.now() / 1000 - ts) <= 300
    && given.length === expected.length
    && crypto.timingSafeEqual(given, Buffer.from(expected));
}
JS;

        $python = <<<'PY'
import hashlib, hmac, json, os, secrets, time
import urllib.error, urllib.parse, urllib.request

BASE = "__BASE__"
KEY_ID = os.environ["TK_CLIENT_ID"]
SECRET = os.environ["TK_CLIENT_SECRET"]

def tk_request(method, path, payload=None, extra_headers=None):
    body = "" if payload is None else json.dumps(payload, separators=(",", ":"), ensure_ascii=False)
    full_path = urllib.parse.urlparse(BASE).path.rstrip("/") + path   # sign the full path
    timestamp = str(int(time.time()))
    nonce = secrets.token_hex(16)
    canonical = "\n".join([method.upper(), full_path, timestamp, nonce,
                           hashlib.sha256(body.encode()).hexdigest()])
    headers = {
        "Content-Type": "application/json", "Accept": "application/json",
        "X-Client-Id": KEY_ID, "X-Timestamp": timestamp, "X-Nonce": nonce,
        "X-Signature": hmac.new(SECRET.encode(), canonical.encode(), hashlib.sha256).hexdigest(),
        **(extra_headers or {}),
    }
    req = urllib.request.Request(BASE + path, data=body.encode() or None, method=method, headers=headers)
    try:
        with urllib.request.urlopen(req, timeout=30) as res:
            return res.status, json.loads(res.read())
    except urllib.error.HTTPError as err:
        return err.code, json.loads(err.read() or b"{}")

def verify_webhook(raw_body: bytes, timestamp: str, signature: str) -> bool:
    expected = hmac.new(os.environ["TK_WEBHOOK_SECRET"].encode(),
                        timestamp.encode() + b"." + raw_body, hashlib.sha256).hexdigest()
    return abs(time.time() - int(timestamp or 0)) <= 300 and hmac.compare_digest(expected, signature)
PY;

        $serverVar = fn (string $header) => strtoupper(str_replace('-', '_', $header));
        $replace = [
            '__BASE__' => $base,
            'HTTP___TS__' => 'HTTP_'.$serverVar(WebhookHeaders::timestamp()),
            'HTTP___SIG__' => 'HTTP_'.$serverVar(WebhookHeaders::signature()),
            'HTTP___DID__' => 'HTTP_'.$serverVar(WebhookHeaders::deliveryId()),
            '__TS__' => WebhookHeaders::timestamp(),
            '__SIG__' => WebhookHeaders::signature(),
        ];

        return array_map(fn ($code) => strtr($code, $replace), [
            'php' => $php,
            'php_webhook' => $webhook,
            'node' => $node,
            'python' => $python,
        ]);
    }
}
