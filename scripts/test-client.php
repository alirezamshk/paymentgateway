<?php

/**
 * Minimal client for smoke-testing a deployment, acting like an external site.
 * Uses only PHP + curl (no framework), so it can be copied anywhere.
 *
 * Usage:
 *   export TK_BASE_URL=https://example.com/payment   # APP_URL of the service (with sub-directory, if any)
 *   export TK_KEY_ID=tkc_...                          # X-Client-Id (30 characters)
 *   export TK_SECRET=tksk_...                         # client secret (69 characters)
 *
 *   php scripts/test-client.php create [amount] [currency]   # default 10000 IRR
 *   php scripts/test-client.php status pay_...
 *   php scripts/test-client.php verify pay_...
 *   php scripts/test-client.php cancel pay_...
 *   php scripts/test-client.php merchants
 *
 * On the server itself you can load the newest active credential straight from the database
 * (avoids copy/paste mistakes):
 *   export TK_KEY_ID=$(php artisan tinker --execute='echo App\Models\ClientCredential::where("status","active")->latest("id")->value("key_id");')
 *   export TK_SECRET=$(php artisan tinker --execute='echo App\Models\ClientCredential::where("status","active")->latest("id")->first()->secret();')
 */
$base = rtrim((string) getenv('TK_BASE_URL'), '/');
$keyId = (string) getenv('TK_KEY_ID');
$secret = (string) getenv('TK_SECRET');

if ($base === '' || $keyId === '' || $secret === '') {
    fwrite(STDERR, "Set TK_BASE_URL, TK_KEY_ID and TK_SECRET first.\n");
    exit(1);
}

if (strlen($keyId) !== 30 || strlen($secret) !== 69) {
    fwrite(STDERR, 'Warning: unexpected lengths (key '.strlen($keyId).'/30, secret '.strlen($secret)."/69) - check for copy/paste mistakes.\n");
}

// The signed path must be the full path, including any installation sub-directory.
$prefix = (string) parse_url($base, PHP_URL_PATH);
$origin = substr($base, 0, strlen($base) - strlen($prefix));

function tk_call(string $method, string $path, ?array $payload): void
{
    global $origin, $keyId, $secret;

    $body = $payload === null ? '' : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $timestamp = (string) time();
    $nonce = bin2hex(random_bytes(16));
    $canonical = implode("\n", [$method, $path, $timestamp, $nonce, hash('sha256', $body)]);

    $ch = curl_init($origin.$path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 40,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Client-Id: '.$keyId,
            'X-Timestamp: '.$timestamp,
            'X-Nonce: '.$nonce,
            'X-Signature: '.hash_hmac('sha256', $canonical, $secret),
        ],
        CURLOPT_POSTFIELDS => $body === '' ? null : $body,
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    echo "HTTP {$status} {$error}\n";
    $decoded = json_decode((string) $response, true);
    echo $decoded === null ? $response : json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
}

$command = $argv[1] ?? '';
$id = $argv[2] ?? '';

match (true) {
    $command === 'create' => tk_call('POST', $prefix.'/api/v1/payments', [
        'order_id' => 'TEST-'.date('YmdHis'),
        'amount' => (int) ($argv[2] ?? 10000),
        'currency' => $argv[3] ?? 'IRR',
        'description' => 'Smoke test payment',
    ]),
    $command === 'status' && $id !== '' => tk_call('GET', $prefix.'/api/v1/payments/'.$id, null),
    $command === 'verify' && $id !== '' => tk_call('POST', $prefix.'/api/v1/payments/'.$id.'/verify', null),
    $command === 'cancel' && $id !== '' => tk_call('POST', $prefix.'/api/v1/payments/'.$id.'/cancel', null),
    $command === 'merchants' => tk_call('GET', $prefix.'/api/v1/merchants', null),
    default => print ("Usage: php scripts/test-client.php create [amount] [currency] | status|verify|cancel <payment_id> | merchants\n"),
};
