<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Holds the current request id so it can be attached to logs, events, errors and webhooks.
 */
final class RequestContext
{
    private static ?string $requestId = null;

    public static function set(string $requestId): void
    {
        self::$requestId = $requestId;
    }

    public static function id(): ?string
    {
        return self::$requestId;
    }

    /** Request id for the current request, or a fresh one for console/queue contexts. */
    public static function idOrNew(): string
    {
        return self::$requestId ??= 'req_'.strtolower((string) Str::ulid());
    }

    public static function reset(): void
    {
        self::$requestId = null;
    }
}
