<?php

namespace App\Security;

/**
 * Client request signature (HMAC-SHA256).
 *
 * canonical = METHOD + "\n" + PATH + "\n" + TIMESTAMP + "\n" + NONCE + "\n" + hex(sha256(BODY))
 * signature = hex(hmac_sha256(client_secret, canonical))
 *
 * PATH is the full request path with a leading slash (including the installation
 * sub-directory, if any), followed by "?" and the raw query string when one is present
 * (e.g. "/api/v1/payments/pay_01j..." or "/payment/api/v1/payments/pay_01j...").
 */
final class RequestSigner
{
    public static function canonical(string $method, string $pathWithQuery, string $timestamp, string $nonce, string $body): string
    {
        return implode("\n", [
            strtoupper($method),
            $pathWithQuery,
            $timestamp,
            $nonce,
            hash('sha256', $body),
        ]);
    }

    public static function sign(string $secret, string $method, string $pathWithQuery, string $timestamp, string $nonce, string $body): string
    {
        return hash_hmac('sha256', self::canonical($method, $pathWithQuery, $timestamp, $nonce, $body), $secret);
    }

    /** Constant-time comparison. */
    public static function matches(string $expected, string $given): bool
    {
        return hash_equals($expected, strtolower(trim($given)));
    }
}
