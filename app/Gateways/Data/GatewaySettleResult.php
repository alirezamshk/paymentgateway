<?php

namespace App\Gateways\Data;

final class GatewaySettleResult
{
    public function __construct(
        public readonly bool $successful,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly array $raw = [],
    ) {}
}
