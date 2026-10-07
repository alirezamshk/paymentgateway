<?php

namespace App\Webhooks;

/**
 * Webhook signature: hex(HMAC-SHA256(webhook_secret, "{timestamp}.{raw_body}")).
 * Sent as X-TK-Signature together with X-TK-Timestamp.
 */
final class WebhookSigner
{
    public static function sign(string $secret, int $timestamp, string $body): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    public static function verify(string $secret, int $timestamp, string $body, string $signature, int $tolerance = 300, ?int $now = null): bool
    {
        if (abs(($now ?? time()) - $timestamp) > $tolerance) {
            return false;
        }

        return hash_equals(self::sign($secret, $timestamp, $body), strtolower($signature));
    }
}
