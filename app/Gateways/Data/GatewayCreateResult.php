<?php

namespace App\Gateways\Data;

final class GatewayCreateResult
{
    /**
     * @param  array<string, mixed>  $request  request sent to PSP (masked before storage)
     * @param  array<string, mixed>  $response  PSP response (masked before storage)
     */
    private function __construct(
        public readonly bool $successful,
        public readonly ?RedirectInstruction $redirect,
        public readonly ?string $authority,
        public readonly ?string $token,
        public readonly ?string $errorCode,
        public readonly ?string $errorMessage,
        public readonly array $request,
        public readonly array $response,
    ) {}

    public static function success(RedirectInstruction $redirect, ?string $authority, ?string $token, array $request = [], array $response = []): self
    {
        return new self(true, $redirect, $authority, $token, null, null, $request, $response);
    }

    public static function failure(string $errorCode, string $errorMessage, array $request = [], array $response = []): self
    {
        return new self(false, null, null, null, $errorCode, $errorMessage, $request, $response);
    }
}
