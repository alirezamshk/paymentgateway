<?php

namespace App\Reports;

use App\Enums\PaymentStatus;
use App\Models\Client;
use App\Models\GatewayProvider;
use App\Support\Display;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Paid volume per day / week / month, split by gateway (provider) or by client.
 *
 * Buckets follow the admin's language: Persian uses Saturday-based weeks and Jalali months,
 * English uses Monday-based weeks and Gregorian months. All bucketing is in Tehran time and
 * all amounts are Rials (IRT × 10).
 */
class SalesReport
{
    public const PERIODS = ['day' => 30, 'week' => 12, 'month' => 12];

    /** Categorical slots in fixed order; colour follows the entity (by id), never its rank. */
    public const MAX_SERIES = 7;

    /**
     * @return array{
     *   period: string, group: string,
     *   buckets: list<array{key: string, label: string, long: string}>,
     *   series: list<array{key: string, name: string, slot: int, total: int, count: int}>,
     *   values: array<string, array<string, int>>, counts: array<string, array<string, int>>,
     *   bucket_totals: array<string, int>, total: int, count: int, failed: int, from: Carbon
     * }
     */
    public function build(string $period, string $group, ?int $clientId, string $locale): array
    {
        $period = array_key_exists($period, self::PERIODS) ? $period : 'day';
        $group = in_array($group, ['provider', 'client'], true) ? $group : 'provider';
        $fa = $locale === 'fa';

        $buckets = $this->buckets($period, $fa);
        $from = $buckets[0]['start'];
        $fromUtc = $from->copy()->utc();

        // Entities in stable id order → fixed colour slot per entity.
        $entities = $group === 'provider'
            ? GatewayProvider::orderBy('id')->get(['id', 'name'])
            : Client::orderBy('id')->when($clientId, fn ($q) => $q->whereKey($clientId))->get(['id', 'name']);
        $slotOf = [];
        foreach ($entities->values() as $i => $entity) {
            $slotOf[$entity->id] = $i;
        }

        $values = $counts = [];
        $bucketOf = $this->bucketResolver($period, $fa);
        $column = $group === 'provider' ? 'provider_id' : 'client_id';

        DB::table('payments')
            ->where('status', PaymentStatus::Paid->value)
            ->where('is_test', false)
            ->where('paid_at', '>=', $fromUtc)
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->orderBy('id')
            ->select(['id', 'paid_at', 'amount', 'currency', $column.' as entity'])
            ->lazyById(1000)
            ->each(function ($row) use (&$values, &$counts, $bucketOf, $slotOf) {
                $key = $bucketOf(Carbon::parse($row->paid_at, 'UTC')->setTimezone(Display::TIMEZONE));
                $entity = $slotOf[$row->entity] ?? null;

                if ($key === null || $entity === null) {
                    return;
                }

                $series = $entity >= self::MAX_SERIES ? 'other' : (string) $row->entity;
                $amount = (int) $row->amount * ($row->currency === 'IRT' ? 10 : 1);
                $values[$key][$series] = ($values[$key][$series] ?? 0) + $amount;
                $counts[$key][$series] = ($counts[$key][$series] ?? 0) + 1;
            });

        $series = [];
        foreach ($entities as $entity) {
            $key = (string) $entity->id;
            $slot = $slotOf[$entity->id];

            if ($slot >= self::MAX_SERIES) {
                continue;
            }

            $series[] = ['key' => $key, 'name' => $entity->name, 'slot' => $slot + 1] + $this->sums($values, $counts, $key);
        }

        if ($entities->count() > self::MAX_SERIES) {
            $series[] = ['key' => 'other', 'name' => $fa ? 'سایر' : 'Other', 'slot' => 0] + $this->sums($values, $counts, 'other');
        }

        // Only series with sales in the window are drawn; colours stay tied to the entity.
        $series = array_values(array_filter($series, fn ($s) => $s['count'] > 0));

        $bucketTotals = [];
        foreach ($buckets as $b) {
            $bucketTotals[$b['key']] = array_sum($values[$b['key']] ?? []);
        }

        $failed = DB::table('payments')
            ->where('status', PaymentStatus::Failed->value)
            ->where('is_test', false)
            ->where('created_at', '>=', $fromUtc)
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->count();

        return [
            'period' => $period,
            'group' => $group,
            'buckets' => array_map(fn ($b) => ['key' => $b['key'], 'label' => $b['label'], 'long' => $b['long']], $buckets),
            'series' => $series,
            'values' => $values,
            'counts' => $counts,
            'bucket_totals' => $bucketTotals,
            'total' => array_sum($bucketTotals),
            'count' => array_sum(array_map(fn ($s) => $s['count'], $series)),
            'failed' => $failed,
            'from' => $from,
        ];
    }

    /** @return array{total: int, count: int} */
    private function sums(array $values, array $counts, string $series): array
    {
        $total = $count = 0;

        foreach ($values as $bucket => $bySeries) {
            $total += $bySeries[$series] ?? 0;
            $count += $counts[$bucket][$series] ?? 0;
        }

        return ['total' => $total, 'count' => $count];
    }

    /**
     * Bucket list, oldest first, ending with the current period.
     *
     * @return list<array{key: string, label: string, long: string, start: Carbon}>
     */
    private function buckets(string $period, bool $fa): array
    {
        $now = Carbon::now(Display::TIMEZONE);
        $n = self::PERIODS[$period];
        $out = [];

        if ($period === 'day') {
            for ($i = $n - 1; $i >= 0; $i--) {
                $d = $now->copy()->startOfDay()->subDays($i);
                [$jy, $jm, $jd] = Display::toJalali($d->year, $d->month, $d->day);
                $out[] = [
                    'key' => $d->format('Y-m-d'),
                    'label' => $fa ? sprintf('%02d/%02d', $jm, $jd) : $d->format('M j'),
                    'long' => $fa ? sprintf('%04d/%02d/%02d', $jy, $jm, $jd) : $d->format('Y-m-d (D)'),
                    'start' => $d,
                ];
            }

            return $out;
        }

        if ($period === 'week') {
            $weekStart = $this->weekStart($now->copy()->startOfDay(), $fa);

            for ($i = $n - 1; $i >= 0; $i--) {
                $d = $weekStart->copy()->subWeeks($i);
                [$jy, $jm, $jd] = Display::toJalali($d->year, $d->month, $d->day);
                $out[] = [
                    'key' => $d->format('Y-m-d'),
                    'label' => $fa ? sprintf('%02d/%02d', $jm, $jd) : $d->format('M j'),
                    'long' => ($fa ? 'هفتهٔ شروع از ' : 'Week of ').($fa ? sprintf('%04d/%02d/%02d', $jy, $jm, $jd) : $d->format('Y-m-d')),
                    'start' => $d,
                ];
            }

            return $out;
        }

        // Months: Jalali for Persian, Gregorian for English.
        if ($fa) {
            [$jy, $jm] = Display::toJalali($now->year, $now->month, $now->day);

            for ($i = $n - 1; $i >= 0; $i--) {
                $m = $jm - $i;
                $y = $jy;
                while ($m < 1) {
                    $m += 12;
                    $y--;
                }
                [$gy, $gm, $gd] = Display::toGregorian($y, $m, 1);
                $out[] = [
                    'key' => sprintf('%04d-%02d', $y, $m),
                    'label' => Display::JALALI_MONTHS[$m - 1],
                    'long' => Display::JALALI_MONTHS[$m - 1].' '.$y,
                    'start' => Carbon::create($gy, $gm, $gd, 0, 0, 0, Display::TIMEZONE),
                ];
            }

            return $out;
        }

        for ($i = $n - 1; $i >= 0; $i--) {
            $d = $now->copy()->startOfMonth()->subMonthsNoOverflow($i);
            $out[] = ['key' => $d->format('Y-m'), 'label' => $d->format('M'), 'long' => $d->format('F Y'), 'start' => $d];
        }

        return $out;
    }

    /** @return callable(Carbon): ?string  maps a Tehran-local time to its bucket key */
    private function bucketResolver(string $period, bool $fa): callable
    {
        return match ($period) {
            'day' => fn (Carbon $t) => $t->format('Y-m-d'),
            'week' => fn (Carbon $t) => $this->weekStart($t->copy()->startOfDay(), $fa)->format('Y-m-d'),
            default => $fa
                ? function (Carbon $t) {
                    [$jy, $jm] = Display::toJalali($t->year, $t->month, $t->day);

                    return sprintf('%04d-%02d', $jy, $jm);
                }
            : fn (Carbon $t) => $t->format('Y-m'),
        };
    }

    /** Persian week starts on Saturday, English on Monday. */
    private function weekStart(Carbon $day, bool $fa): Carbon
    {
        $first = $fa ? Carbon::SATURDAY : Carbon::MONDAY;
        $diff = ($day->dayOfWeek - $first + 7) % 7;

        return $day->copy()->subDays($diff);
    }
}
