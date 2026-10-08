<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Admin display helpers: dates in Tehran time (Jalali for Persian, Gregorian for English)
 * and amounts in Rials with a Toman hint.
 */
final class Display
{
    public const TIMEZONE = 'Asia/Tehran';

    public static function date(?DateTimeInterface $value, bool $withTime = true): string
    {
        if ($value === null) {
            return '—';
        }

        $local = Carbon::instance($value)->setTimezone(self::TIMEZONE);
        $time = $withTime ? ' '.$local->format('H:i') : '';

        if (app()->getLocale() !== 'fa') {
            return $local->format('Y-m-d').$time;
        }

        [$y, $m, $d] = self::toJalali((int) $local->format('Y'), (int) $local->format('n'), (int) $local->format('j'));

        return sprintf('%04d/%02d/%02d', $y, $m, $d).$time;
    }

    /** "1,250,000 ریال" / "1,250,000 IRR". */
    public static function rial(int $amount): string
    {
        return number_format($amount).' '.(app()->getLocale() === 'fa' ? 'ریال' : 'IRR');
    }

    /** "125,000 تومان" / "125,000 Toman" (exact Rial ÷ 10, rounded down). */
    public static function toman(int $rials): string
    {
        return number_format(intdiv($rials, 10)).' '.(app()->getLocale() === 'fa' ? 'تومان' : 'Toman');
    }

    /**
     * Gregorian → Jalali (Solar Hijri).
     *
     * @return array{0: int, 1: int, 2: int}
     */
    public static function toJalali(int $gy, int $gm, int $gd): array
    {
        $gDaysInMonth = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = $gm > 2 ? $gy + 1 : $gy;
        $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $gDaysInMonth[$gm - 1];
        $jy = -1595 + (33 * intdiv($days, 12053));
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;

        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        if ($days < 186) {
            return [$jy, 1 + intdiv($days, 31), 1 + ($days % 31)];
        }

        return [$jy, 7 + intdiv($days - 186, 30), 1 + (($days - 186) % 30)];
    }
}
