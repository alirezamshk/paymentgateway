<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\ClientCredential;
use App\Security\RequestSigner;
use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * HMAC authentication for external client sites.
 *
 * Required headers: X-Client-Id (credential key id), X-Timestamp (unix seconds),
 * X-Nonce (16-64 chars [A-Za-z0-9_-]), X-Signature (hex HMAC-SHA256, see RequestSigner).
 *
 * Referer, Origin and User-Agent are never used for authentication.
 */
class AuthenticateClient
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $keyId = (string) $request->header('X-Client-Id', '');
        $timestamp = (string) $request->header('X-Timestamp', '');
        $nonce = (string) $request->header('X-Nonce', '');
        $signature = (string) $request->header('X-Signature', '');

        if ($keyId === '' || $timestamp === '' || $nonce === '' || $signature === '') {
            $this->fail($keyId, 'missing_headers', 'AUTH_REQUIRED', 'Authentication headers are missing.');
        }

        if (! ctype_digit($timestamp) || ! preg_match('/^[A-Za-z0-9_-]{16,64}$/', $nonce) || strlen($keyId) > 64) {
            $this->fail($keyId, 'malformed_headers', 'AUTH_INVALID', 'Authentication headers are malformed.');
        }

        $tolerance = (int) config('payments.auth.timestamp_tolerance');

        if (abs(time() - (int) $timestamp) > $tolerance) {
            $this->fail($keyId, 'timestamp_expired', 'AUTH_TIMESTAMP_EXPIRED', 'The request timestamp is outside the allowed window.');
        }

        $credential = ClientCredential::with('client')->where('key_id', $keyId)->first();

        // Compute a signature even for unknown keys so timing does not reveal key existence.
        $secret = $credential?->secret() ?? str_repeat('0', 64);
        $pathWithQuery = $request->getPathInfo().(($qs = $request->server('QUERY_STRING')) ? '?'.$qs : '');
        $expected = RequestSigner::sign($secret, $request->getMethod(), $pathWithQuery, $timestamp, $nonce, $request->getContent());

        if ($credential === null || ! RequestSigner::matches($expected, $signature)) {
            $this->fail($keyId, 'invalid_signature', 'AUTH_INVALID_SIGNATURE', 'The request signature is invalid.', $credential?->client_id);
        }

        if (! $credential->isActive() || ! $credential->client?->isActive()) {
            $this->fail($keyId, 'inactive', 'AUTH_CLIENT_DISABLED', 'This client or credential is disabled.', $credential->client_id, 403);
        }

        // Nonce is checked only after the signature, so unauthenticated callers cannot burn nonces.
        $nonceStore = Cache::store(config('payments.auth.nonce_store'));

        if (! $nonceStore->add("client-nonce:{$keyId}:{$nonce}", 1, $tolerance * 2 + 60)) {
            $this->fail($keyId, 'nonce_replayed', 'AUTH_NONCE_REPLAYED', 'This nonce has already been used.', $credential->client_id);
        }

        if ($credential->last_used_at === null || $credential->last_used_at->lt(now()->subMinute())) {
            $credential->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        $request->attributes->set('client', $credential->client);
        $request->attributes->set('client_credential', $credential);

        return $next($request);
    }

    private function fail(string $keyId, string $reason, string $code, string $message, ?int $clientId = null, int $status = 401): never
    {
        $this->audit->log('client', null, 'client.auth_failed', $clientId, 'client_credential', mb_substr($keyId, 0, 64) ?: null, ['reason' => $reason]);

        throw new ApiException($code, $message, $status);
    }
}
