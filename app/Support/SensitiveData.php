<?php

namespace App\Support;

/**
 * Masks secrets and card data before anything is logged or persisted as a raw payload.
 */
final class SensitiveData
{
    /** Keys whose values are always fully redacted (compared case-insensitively, without _ and -). */
    private const REDACT_KEYS = [
        'password', 'pwd', 'pass', 'secret', 'clientsecret', 'webhooksecret', 'apikey', 'apisecret',
        'merchantid', 'merchant', 'merchantconfigurationid', 'terminalid', 'tid', 'usr', 'username',
        'authorization', 'xsignature', 'signature', 'cvv', 'cvv2', 'pin', 'pin2', 'expiry', 'expdate',
        'encryptedpassword', 'encryptedapikey', 'encryptedconfig', 'encryptedsecret', 'accesstoken',
        'privatekey', 'key', 'apitoken', 'refreshtoken', 'xapikey', 'cookie', 'setcookie', 'hmac', 'iban',
    ];

    /** Any key containing one of these is redacted too (e.g. merchant_password, sepehr_api_secret). */
    private const REDACT_FRAGMENTS = ['secret', 'password', 'passwd', 'apikey', 'privatekey'];

    /** Keys whose values are card numbers and must be masked. */
    private const CARD_KEYS = ['cardnumber', 'pan', 'cardpan', 'card', 'maskedpan', 'cardno'];

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function mask(array $data): array
    {
        $out = [];

        foreach ($data as $key => $value) {
            $normalized = is_string($key) ? strtolower(str_replace(['_', '-'], '', $key)) : null;

            if ($normalized !== null && (in_array($normalized, self::REDACT_KEYS, true) || self::containsFragment($normalized))) {
                $out[$key] = $value === null || $value === '' ? $value : '[REDACTED]';
            } elseif ($normalized !== null && in_array($normalized, self::CARD_KEYS, true) && is_scalar($value)) {
                $out[$key] = self::maskPan((string) $value);
            } elseif (is_array($value)) {
                $out[$key] = self::mask($value);
            } elseif (is_string($value)) {
                $out[$key] = self::maskPansInText($value);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /** Mask a card number to first6 + **** + last4. Already-masked values are normalized. */
    public static function maskPan(?string $pan): ?string
    {
        if ($pan === null || $pan === '') {
            return $pan;
        }

        $digits = preg_replace('/[^0-9*]/', '', $pan) ?? '';
        $len = strlen($digits);

        if ($len < 10) {
            return str_repeat('*', max($len, 4));
        }

        return substr($digits, 0, 6).str_repeat('*', $len - 10).substr($digits, -4);
    }

    /** Replace anything that looks like a full PAN (16-19 digits passing Luhn) in free text. */
    public static function maskPansInText(string $text): string
    {
        return preg_replace_callback(
            '/(?<!\d)\d{16,19}(?!\d)/',
            static fn (array $m) => self::luhnValid($m[0]) ? self::maskPan($m[0]) : $m[0],
            $text,
        ) ?? $text;
    }

    private static function luhnValid(string $digits): bool
    {
        $sum = 0;
        $double = false;

        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $d = (int) $digits[$i];
            if ($double) {
                $d *= 2;
                if ($d > 9) {
                    $d -= 9;
                }
            }
            $sum += $d;
            $double = ! $double;
        }

        return $sum % 10 === 0;
    }

    private static function containsFragment(string $normalizedKey): bool
    {
        foreach (self::REDACT_FRAGMENTS as $fragment) {
            if (str_contains($normalizedKey, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
