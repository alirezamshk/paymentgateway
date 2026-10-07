<?php

namespace App\Enums;

/**
 * Money convention: every amount is an integer in the unit of its currency.
 *
 *  - IRR: amount is in Rials.
 *  - IRT: amount is in Tomans (1 Toman = 10 Rials).
 *
 * A payment keeps the currency it was created with. The only place a conversion
 * happens is at the PSP boundary (inside an adapter, via App\Support\Money), when
 * a PSP requires a specific unit. That conversion is exact or it fails - it is
 * never rounded.
 */
enum Currency: string
{
    case IRR = 'IRR';
    case IRT = 'IRT';

    /** Number of Rials in one unit of this currency. */
    public function rialFactor(): int
    {
        return match ($this) {
            self::IRR => 1,
            self::IRT => 10,
        };
    }
}
