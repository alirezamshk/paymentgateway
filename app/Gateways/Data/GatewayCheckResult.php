<?php

namespace App\Gateways\Data;

final class GatewayCheckResult
{
    public function __construct(
        public readonly bool $successful,
        public readonly string $message,
    ) {}
}
