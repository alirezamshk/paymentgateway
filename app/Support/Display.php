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

    /**
     * Jalali (Solar Hijri) → Gregorian.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    public static function toGregorian(int $jy, int $jm, int $jd): array
    {
        $jy += 1595;
        $days = -355668 + (365 * $jy) + (intdiv($jy, 33) * 8) + intdiv(($jy % 33) + 3, 4) + $jd + ($jm < 7 ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
        $gy = 400 * intdiv($days, 146097);
        $days %= 146097;

        if ($days > 36524) {
            $gy += 100 * intdiv(--$days, 36524);
            $days %= 36524;

            if ($days >= 365) {
                $days++;
            }
        }

        $gy += 4 * intdiv($days, 1461);
        $days %= 1461;

        if ($days > 365) {
            $gy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        $gd = $days + 1;
        $leap = ($gy % 4 === 0 && $gy % 100 !== 0) || $gy % 400 === 0;
        $monthDays = [0, 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

        for ($gm = 0; $gm < 13 && $gd > $monthDays[$gm]; $gm++) {
            $gd -= $monthDays[$gm];
        }

        return [$gy, $gm, $gd];
    }

    public const JALALI_MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

    /** Compact amount for chart axes: 1.2M / ۱٫۲ میلیون style (latin digits). */
    public static function compact(int $value): string
    {
        $fa = app()->getLocale() === 'fa';
        $units = $fa ? [[1e9, ' میلیارد'], [1e6, ' میلیون'], [1e3, ' هزار']] : [[1e9, 'B'], [1e6, 'M'], [1e3, 'K']];

        foreach ($units as [$size, $suffix]) {
            if (abs($value) >= $size) {
                return rtrim(rtrim(number_format($value / $size, 1, '.', ''), '0'), '.').$suffix;
            }
        }

        return (string) $value;
    }
}
