<?php

namespace App\Reports;

use App\Support\Display;

/**
 * Server-side geometry for a stacked column chart (inline SVG, no JavaScript).
 * Marks: 2px surface gap between stacked segments, 4px rounded data-end on the top
 * segment only, recessive grid, native <title> tooltips on every segment and column.
 */
class StackedColumnChart
{
    public const WIDTH = 1000;

    public const HEIGHT = 300;

    private const PAD = ['top' => 14, 'right' => 10, 'bottom' => 30, 'left' => 72];

    /**
     * @param  array<string, mixed>  $report  output of SalesReport::build()
     * @return array{width: int, height: int, ticks: list<array{y: float, label: string}>, columns: list<array<string, mixed>>, baseline: float, plot: array<string, float>}
     */
    public static function build(array $report): array
    {
        $plotW = self::WIDTH - self::PAD['left'] - self::PAD['right'];
        $plotH = self::HEIGHT - self::PAD['top'] - self::PAD['bottom'];
        $max = self::niceCeil(max([1, ...array_values($report['bucket_totals'])]));
        $n = max(1, count($report['buckets']));
        $band = $plotW / $n;
        $barW = min($band * 0.62, 38);
        $labelEvery = $n > 20 ? 3 : ($n > 14 ? 2 : 1);
        $baseline = self::PAD['top'] + $plotH;
        $scale = fn (int $v) => $v / $max * $plotH;

        $ticks = [];
        for ($i = 0; $i <= 4; $i++) {
            $v = (int) round($max * $i / 4);
            $ticks[] = ['y' => round($baseline - $scale($v), 2), 'label' => Display::compact($v)];
        }

        $columns = [];
        foreach ($report['buckets'] as $i => $bucket) {
            $x = self::PAD['left'] + $band * $i + ($band - $barW) / 2;
            $y = $baseline;
            $segments = [];
            $present = array_values(array_filter($report['series'], fn ($s) => ($report['values'][$bucket['key']][$s['key']] ?? 0) > 0));

            foreach ($present as $j => $s) {
                $amount = $report['values'][$bucket['key']][$s['key']];
                $h = $scale($amount);
                $top = $y - $h;
                $isTop = $j === count($present) - 1;
                // 2px surface gap under every segment except the one on the baseline.
                $drawBottom = $j === 0 ? $y : $y - 2;
                $segments[] = [
                    'slot' => $s['slot'],
                    'path' => self::segmentPath($x, $top, $barW, max(0, $drawBottom - $top), $isTop ? 4 : 0),
                    'title' => $bucket['long'].' — '.$s['name'].': '.Display::rial($amount).' ('.($report['counts'][$bucket['key']][$s['key']] ?? 0).')',
                ];
                $y = $top;
            }

            $columns[] = [
                'hit' => ['x' => round(self::PAD['left'] + $band * $i, 2), 'w' => round($band, 2)],
                'label_x' => round($x + $barW / 2, 2),
                'label' => $i % $labelEvery === 0 || $i === $n - 1 ? $bucket['label'] : null,
                'total_title' => $bucket['long'].' — '.Display::rial($report['bucket_totals'][$bucket['key']]),
                'segments' => $segments,
            ];
        }

        return [
            'width' => self::WIDTH,
            'height' => self::HEIGHT,
            'ticks' => $ticks,
            'columns' => $columns,
            'baseline' => $baseline,
            'plot' => ['x' => self::PAD['left'], 'y' => self::PAD['top'], 'w' => $plotW, 'h' => $plotH],
        ];
    }

    /** Rectangle with only the top corners rounded (data-end), bottom anchored to the stack. */
    private static function segmentPath(float $x, float $y, float $w, float $h, float $r): string
    {
        $f = fn (float $v) => round($v, 2);

        if ($h <= 0) {
            return '';
        }

        $r = min($r, $h, $w / 2);

        if ($r <= 0) {
            return "M{$f($x)},{$f($y)}h{$f($w)}v{$f($h)}h{$f(-$w)}z";
        }

        return 'M'.$f($x).','.$f($y + $h)
            .'V'.$f($y + $r)
            .'Q'.$f($x).','.$f($y).' '.$f($x + $r).','.$f($y)
            .'H'.$f($x + $w - $r)
            .'Q'.$f($x + $w).','.$f($y).' '.$f($x + $w).','.$f($y + $r)
            .'V'.$f($y + $h).'Z';
    }

    /** 1, 2, 2.5, 5 × 10^k ceiling for readable axis ticks (4 intervals). */
    private static function niceCeil(int $value): int
    {
        $step = $value / 4;
        $magnitude = 10 ** floor(log10(max(1, $step)));

        foreach ([1, 2, 2.5, 5, 10] as $m) {
            if ($m * $magnitude >= $step) {
                return (int) ($m * $magnitude * 4);
            }
        }

        return $value;
    }
}
