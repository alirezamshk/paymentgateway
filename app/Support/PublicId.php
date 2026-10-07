<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Opaque, non-sequential public identifiers (prefix + lowercase ULID).
 */
final class PublicId
{
    public static function make(string $prefix): string
    {
        return $prefix.'_'.strtolower((string) Str::ulid());
    }

    public static function isValid(string $value, string $prefix): bool
    {
        return (bool) preg_match('/^'.preg_quote($prefix, '/').'_[0-9a-hjkmnp-tv-z]{26}$/', $value);
    }
}
