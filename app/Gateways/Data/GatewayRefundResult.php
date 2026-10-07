<?php

namespace App\Gateways\Data;

final class GatewayRefundResult
{
    public function __construct(
        public readonly bool $successful,
        public readonly ?string $referenceNumber = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly array $raw = [],
    ) {}
}
