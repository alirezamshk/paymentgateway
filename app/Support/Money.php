<?php

namespace App\Support;

use App\Enums\Currency;
use App\Exceptions\ApiException;

/**
 * Exact integer conversions between currency units. Used only at the PSP boundary.
 */
final class Money
{
    /** Convert an integer amount from one currency unit to another, failing if not exact. */
    public static function convert(int $amount, Currency $from, Currency $to): int
    {
        if ($from === $to) {
            return $amount;
        }

        $rials = $amount * $from->rialFactor();

        if ($rials % $to->rialFactor() !== 0) {
            throw new ApiException(
                'AMOUNT_NOT_CONVERTIBLE',
                "Amount {$amount} {$from->value} cannot be represented exactly in {$to->value}.",
                422,
            );
        }

        return intdiv($rials, $to->rialFactor());
    }
}
