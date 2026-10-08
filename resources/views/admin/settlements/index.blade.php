@extends('admin.layout')
@php use App\Support\Display; @endphp
@section('title', __('Settlements'))
@section('content')
<div class="stats">
    <div class="stat"><div class="ic">@include('admin._icon', ['name' => 'settlements', 'size' => 20])</div><div><div class="label">{{ __('Total owed to clients') }}</div><div class="value">{{ Display::rial($totals['balance']) }}</div><div class="sub">{{ Display::toman($totals['balance']) }}</div></div></div>
    <div class="stat"><div class="ic success">@include('admin._icon', ['name' => 'check', 'size' => 20])</div><div><div class="label">{{ __('Payable now') }}</div><div class="value">{{ Display::rial($totals['available']) }}</div><div class="sub">{{ Display::toman($totals['available']) }}</div></div></div>
    <div class="stat"><div class="ic warning">@include('admin._icon', ['name' => 'payments', 'size' => 20])</div><div><div class="label">{{ __('Commission earned') }}</div><div class="value">{{ Display::rial($totals['commission']) }}</div></div></div>
    <div class="stat"><div class="ic">@include('admin._icon', ['name' => 'download', 'size' => 20])</div><div><div class="label">{{ __('Paid out') }}</div><div class="value">{{ Display::rial($totals['paid_out']) }}</div></div></div>
</div>
<div class="card">
    <div class="card-head"><h2>{{ __('Balances by client') }}</h2><span class="muted small">{{ __('Amounts in Rial') }}</span></div>
    <div class="card-body flush"><div class="table-wrap">
    <table>
        <thead><tr><th>{{ __('Client') }}</th><th>{{ __('Commission') }}</th><th class="num">{{ __('Paid in') }}</th><th class="num">{{ __('Commission') }}</th><th class="num">{{ __('Paid out') }}</th><th class="num">{{ __('Balance') }}</th><th class="num">{{ __('Payable now') }}</th><th>{{ __('Last payout') }}</th><th></th></tr></thead>
        <tbody>
        @forelse($rows as $r)
            @php $s = $r['summary']; @endphp
            <tr>
                <td><strong>{{ $r['client']->name }}</strong>@unless($r['client']->iban)<div class="small" style="color:var(--warning)">{{ __('No IBAN') }}</div>@endunless</td>
                <td class="small">@include('admin.settlements._commission', ['client' => $r['client']])</td>
                <td class="num">{{ number_format($s['paid_in']) }}</td>
                <td class="num neg"><span class="ltr">{{ $s['commission'] ? '−'.number_format($s['commission']) : '0' }}</span></td>
                <td class="num">{{ number_format($s['paid_out']) }}</td>
                <td class="num"><strong>{{ number_format($s['balance']) }}</strong></td>
                <td class="num pos"><strong>{{ number_format($s['available']) }}</strong></td>
                <td class="small ltr">{{ $r['last_payout'] ? Display::date($r['last_payout']->paid_on, false) : '—' }}</td>
                <td class="num"><a class="btn sm {{ $s['available'] > 0 ? '' : 'secondary' }}" href="{{ route('admin.settlements.show', $r['client']) }}">{{ __('Open') }}</a></td>
            </tr>
        @empty
            <tr><td colspan="9" class="empty">{{ __('No clients yet.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
    </div></div>
</div>
@endsection
