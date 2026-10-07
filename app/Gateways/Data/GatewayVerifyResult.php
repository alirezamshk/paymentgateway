<?php

namespace App\Gateways\Data;

/**
 * Authoritative PSP verification outcome. Only `verified()` leads to a paid payment.
 */
final class GatewayVerifyResult
{
    /**
     * @param  array<string, mixed>  $raw  PSP traffic (masked before storage)
     * @param  array<string, mixed>  $extra  provider data needed later (e.g. settlement ids)
     */
    private function __construct(
        public readonly bool $verified,
        public readonly ?string $referenceNumber,
        public readonly ?string $traceNumber,
        public readonly ?string $cardMask,
        public readonly ?string $errorCode,
        public readonly ?string $errorMessage,
        public readonly array $raw,
        public readonly array $extra,
    ) {}

    public static function verified(?string $referenceNumber, ?string $traceNumber = null, ?string $cardMask = null, array $raw = [], array $extra = []): self
    {
        return new self(true, $referenceNumber, $traceNumber, $cardMask, null, null, $raw, $extra);
    }

    public static function rejected(string $errorCode, string $errorMessage, array $raw = []): self
    {
        return new self(false, null, null, null, $errorCode, $errorMessage, $raw, []);
    }
}
