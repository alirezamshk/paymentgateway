<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised by adapters for transport-level or unexpected PSP failures where the outcome
 * is unknown (timeouts, 5xx, malformed responses). Business rejections are returned as
 * result objects instead.
 */
class GatewayException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $response  What the PSP answered, if anything (stored masked, for diagnosis).
     */
    public function __construct(string $message, public readonly ?string $pspCode = null, ?\Throwable $previous = null, public readonly array $response = [])
    {
        parent::__construct($message, 0, $previous);
    }
}
