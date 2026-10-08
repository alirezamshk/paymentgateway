<?php

namespace App\Support;

/**
 * Iranian IBAN (Sheba): "IR" + 24 digits, ISO 13616 mod-97 checksum.
 */
final class Iban
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $digits = Mobile::digits($value);

        return strlen($digits) === 24 ? 'IR'.$digits : null;
    }

    public static function isValid(?string $value): bool
    {
        $iban = self::normalize($value);

        if ($iban === null) {
            return false;
        }

        // Move country code + check digits to the end, letters → numbers (I=18, R=27), then mod 97.
        $rearranged = substr($iban, 4).'1827'.substr($iban, 2, 2);
        $remainder = 0;

        foreach (str_split($rearranged) as $char) {
            $remainder = ($remainder * 10 + (int) $char) % 97;
        }

        return $remainder === 1;
    }
}
