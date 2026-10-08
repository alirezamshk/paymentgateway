<?php

namespace App\Support;

/**
 * Iranian mobile numbers: normalizes Persian/Arabic digits and +98/0098/98 prefixes to 09xxxxxxxxx.
 */
final class Mobile
{
    public static function digits(string $value): string
    {
        $value = strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        return preg_replace('/\D/', '', $value) ?? '';
    }

    /** Full normalization; returns null when the value is not a valid Iranian mobile number. */
    public static function normalize(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $digits = self::digits($value);
        $digits = preg_replace('/^(0098|98)(?=9\d{9}$)/', '', $digits) ?? $digits;

        if (preg_match('/^9\d{9}$/', $digits)) {
            $digits = '0'.$digits;
        }

        return preg_match('/^09\d{9}$/', $digits) ? $digits : null;
    }

    /** Lenient form for search input (partial numbers allowed). */
    public static function forSearch(string $value): string
    {
        return self::normalize($value) ?? self::digits($value);
    }
}
