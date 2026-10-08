@extends('admin.layout')
@php
    use App\Support\Display;
    $q = fn (array $over) => route('admin.reports.sales', array_filter(array_merge(request()->only('period', 'group', 'client'), $over)));
    $avg = $report['count'] ? intdiv($report['total'], $report['count']) : 0;
    $attempted = $report['count'] + $report['failed'];
    $rate = $attempted ? round($report['count'] / $attempted * 100, 1) : null;
    $periodLabel = ['day' => __('Last 30 days'), 'week' => __('Last 12 weeks'), 'month' => __('Last 12 months')][$report['period']];
@endphp
@section('title', __('Sales report'))
@section('content')
<div class="card">
    <div class="card-body" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center">
        <div class="seg-tabs" role="tablist" aria-label="{{ __('Period') }}">
            @foreach(['day' => __('Daily'), 'week' => __('Weekly'), 'month' => __('Monthly')] as $key => $label)
                <a href="{{ $q(['period' => $key]) }}" class="{{ $report['period'] === $key ? 'on' : '' }}" role="tab" aria-selected="{{ $report['period'] === $key ? 'true' : 'false' }}">{{ $label }}</a>
            @endforeach
        </div>
        <div class="seg-tabs" role="tablist" aria-label="{{ __('Group by') }}">
            @foreach(['provider' => __('By gateway'), 'client' => __('By client')] as $key => $label)
                <a href="{{ $q(['group' => $key]) }}" class="{{ $report['group'] === $key ? 'on' : '' }}" role="tab" aria-selected="{{ $report['group'] === $key ? 'true' : 'false' }}">{{ $label }}</a>
            @endforeach
        </div>
        <form method="GET" style="display:flex;gap:8px;align-items:center;margin-inline-start:auto">
            <input type="hidden" name="period" value="{{ $report['period'] }}"><input type="hidden" name="group" value="{{ $report['group'] }}">
            <select name="client" style="width:auto">
                <option value="">{{ __('All clients') }}</option>
                @foreach($clients as $c)<option value="{{ $c->public_id }}" @selected($selectedClient?->public_id === $c->public_id)>{{ $c->name }}</option>@endforeach
            </select>
            <button class="btn secondary">{{ __('Filter') }}</button>
        </form>
    </div>
</div>

<div class="stats">
    <div class="stat"><div class="ic success">@include('admin._icon', ['name' => 'chart', 'size' => 20])</div><div><div class="label">{{ __('Total sales') }} · {{ $periodLabel }}</div><div class="value">{{ Display::rial($report['total']) }}</div><div class="sub">{{ Display::toman($report['total']) }}</div></div></div>
    <div class="stat"><div class="ic">@include('admin._icon', ['name' => 'payments', 'size' => 20])</div><div><div class="label">{{ __('Successful payments') }}</div><div class="value">{{ number_format($report['count']) }}</div></div></div>
    <div class="stat"><div class="ic">@include('admin._icon', ['name' => 'settlements', 'size' => 20])</div><div><div class="label">{{ __('Average payment') }}</div><div class="value">{{ Display::rial($avg) }}</div></div></div>
    <div class="stat"><div class="ic warning">@include('admin._icon', ['name' => 'check', 'size' => 20])</div><div><div class="label">{{ __('Success rate') }}</div><div class="value">{{ $rate === null ? '—' : $rate.'%' }}</div><div class="sub">{{ __(':failed failed', ['failed' => number_format($report['failed'])]) }}</div></div></div>
</div>

<div class="card">
    <div class="card-head">
        <h2>{{ $report['group'] === 'provider' ? __('Sales by gateway') : __('Sales by client') }}</h2>
        <span class="muted small">{{ $periodLabel }} · {{ __('Amounts in Rial') }}</span>
    </div>
    @if($report['total'] === 0)
        <div class="empty">{{ __('No successful payments in this period.') }}</div>
    @else
        <div class="legend" aria-label="{{ __('Legend') }}">
            @foreach($report['series'] as $s)
                <div class="item"><span class="sw" style="background:var(--series-{{ $s['slot'] }})"></span>{{ $s['name'] }}
                    <span class="val">{{ Display::compact($s['total']) }} · <bdi>{{ round($s['total'] / $report['total'] * 100) }}%</bdi></span></div>
            @endforeach
        </div>
        <div class="card-body">
            <svg class="chart" viewBox="0 0 {{ $chart['width'] }} {{ $chart['height'] }}" role="img" aria-label="{{ $report['group'] === 'provider' ? __('Sales by gateway') : __('Sales by client') }}">
                @foreach($chart['ticks'] as $t)
                    <line class="grid" x1="{{ $chart['plot']['x'] }}" x2="{{ $chart['plot']['x'] + $chart['plot']['w'] }}" y1="{{ $t['y'] }}" y2="{{ $t['y'] }}"/>
                    <text x="{{ $chart['plot']['x'] - 10 }}" y="{{ $t['y'] + 4 }}" text-anchor="end">{{ $t['label'] }}</text>
                @endforeach
                <line class="axis" x1="{{ $chart['plot']['x'] }}" x2="{{ $chart['plot']['x'] + $chart['plot']['w'] }}" y1="{{ $chart['baseline'] }}" y2="{{ $chart['baseline'] }}"/>
                @foreach($chart['columns'] as $col)
                    <g>
                        <rect class="hit" x="{{ $col['hit']['x'] }}" y="{{ $chart['plot']['y'] }}" width="{{ $col['hit']['w'] }}" height="{{ $chart['plot']['h'] }}"><title>{{ $col['total_title'] }}</title></rect>
                        @foreach($col['segments'] as $seg)
                            <path d="{{ $seg['path'] }}" fill="var(--series-{{ $seg['slot'] }})"><title>{{ $seg['title'] }}</title></path>
                        @endforeach
                        @if($col['label'])<text x="{{ $col['label_x'] }}" y="{{ $chart['baseline'] + 20 }}" text-anchor="middle">{{ $col['label'] }}</text>@endif
                    </g>
                @endforeach
            </svg>
            <div class="muted small" style="margin-top:6px">{{ __('Hover a column for exact amounts.') }}</div>
        </div>
    @endif
</div>

<div class="card">
    <div class="card-head"><h3>{{ $report['group'] === 'provider' ? __('Totals by gateway') : __('Totals by client') }}</h3></div>
    <div class="card-body flush"><div class="table-wrap">
    <table>
        <thead><tr><th>{{ $report['group'] === 'provider' ? __('Provider') : __('Client') }}</th><th class="num">{{ __('Payments') }}</th><th class="num">{{ __('Amount (Rial)') }}</th><th class="num">{{ __('Average') }}</th><th class="num">{{ __('Share') }}</th></tr></thead>
        <tbody>
        @forelse($report['series'] as $s)
            <tr>
                <td><span class="sw" style="display:inline-block;width:10px;height:10px;border-radius:3px;background:var(--series-{{ $s['slot'] }});margin-inline-end:8px"></span>{{ $s['name'] }}</td>
                <td class="num">{{ number_format($s['count']) }}</td><td class="num">{{ number_format($s['total']) }}</td>
                <td class="num">{{ number_format($s['count'] ? intdiv($s['total'], $s['count']) : 0) }}</td>
                <td class="num">{{ $report['total'] ? round($s['total'] / $report['total'] * 100, 1) : 0 }}%</td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty">{{ __('No successful payments in this period.') }}</td></tr>
        @endforelse
        </tbody>
        @if($report['series'])
            <tfoot><tr><th>{{ __('Total') }}</th><th class="num">{{ number_format($report['count']) }}</th><th class="num">{{ number_format($report['total']) }}</th><th class="num">{{ number_format($avg) }}</th><th class="num">100%</th></tr></tfoot>
        @endif
    </table>
    </div></div>
</div>

<div class="card">
    <div class="card-head"><h3>{{ __('Breakdown by period') }}</h3><span class="muted small">{{ __('Amounts in Rial') }}</span></div>
    <div class="card-body flush"><div class="table-wrap">
    <table>
        <thead><tr><th>{{ __('Period') }}</th>@foreach($report['series'] as $s)<th class="num">{{ $s['name'] }}</th>@endforeach<th class="num">{{ __('Total') }}</th></tr></thead>
        <tbody>
        @foreach(array_reverse($report['buckets']) as $b)
            <tr>
                <td class="ltr" style="white-space:nowrap">{{ $b['long'] }}</td>
                @foreach($report['series'] as $s)<td class="num">{{ ($v = $report['values'][$b['key']][$s['key']] ?? 0) ? number_format($v) : '—' }}</td>@endforeach
                <td class="num"><strong>{{ $report['bucket_totals'][$b['key']] ? number_format($report['bucket_totals'][$b['key']]) : '—' }}</strong></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div></div>
</div>
@endsection
