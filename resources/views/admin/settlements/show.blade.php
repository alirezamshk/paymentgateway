@extends('admin.layout')
@php use App\Support\Display; @endphp
@section('title', __('Settlement').' — '.$client->name)
@section('actions')
    <a class="btn secondary" href="{{ route('admin.settlements.export', array_merge(['client' => $client], request()->only('from', 'to'))) }}">@include('admin._icon', ['name' => 'download']) {{ __('Export CSV') }}</a>
    <a class="btn secondary" href="{{ route('admin.clients.edit', $client) }}">{{ __('Settlement settings') }}</a>
@endsection
@section('content')
<div class="stats">
    <div class="stat"><div class="ic">@include('admin._icon', ['name' => 'settlements', 'size' => 20])</div><div><div class="label">{{ __('Balance') }}</div><div class="value">{{ Display::rial($summary['balance']) }}</div><div class="sub">{{ Display::toman($summary['balance']) }}</div></div></div>
    <div class="stat"><div class="ic success">@include('admin._icon', ['name' => 'check', 'size' => 20])</div><div><div class="label">{{ __('Payable now') }}</div><div class="value">{{ Display::rial($summary['available']) }}</div><div class="sub">{{ Display::toman($summary['available']) }}</div></div></div>
    <div class="stat"><div class="ic warning">@include('admin._icon', ['name' => 'clock', 'size' => 20])</div><div><div class="label">{{ __('On hold') }}</div><div class="value">{{ Display::rial($summary['pending']) }}</div></div></div>
    <div class="stat"><div class="ic">@include('admin._icon', ['name' => 'payments', 'size' => 20])</div><div><div class="label">{{ __('Commission earned') }}</div><div class="value">{{ Display::rial($summary['commission']) }}</div><div class="sub">@include('admin.settlements._commission', ['client' => $client])</div></div></div>
</div>

<div class="grid-2" style="align-items:start">
    <div class="card">
        <div class="card-head"><h3>{{ __('Record payout') }}</h3></div>
        <div class="card-body">
            <p class="muted small" style="margin-top:0">{{ __('Transfer the money from the bank first, then record it here.') }}</p>
            <dl class="kv" style="margin-bottom:12px">
                <div><dt>{{ __('IBAN (Sheba)') }}</dt><dd class="mono ltr">{{ $client->iban ?: '—' }}</dd></div>
                <div><dt>{{ __('Account holder') }}</dt><dd>{{ $client->account_holder ?: '—' }}</dd></div>
            </dl>
            <form method="POST" action="{{ route('admin.settlements.payouts.store', $client) }}">
                @csrf
                <div class="grid-2">
                    <div class="field"><label>{{ __('Amount') }}</label><input name="amount" type="number" min="1" required value="{{ old('amount', $summary['available'] > 0 ? $summary['available'] : '') }}" dir="ltr"></div>
                    <div class="field"><label>{{ __('Unit') }}</label><select name="unit"><option value="irr">{{ __('IRR') }}</option><option value="toman" @selected(old('unit') === 'toman')>{{ __('IRT') }}</option></select></div>
                    <div class="field"><label>{{ __('Bank reference') }}</label><input name="bank_reference" required value="{{ old('bank_reference') }}" dir="ltr"></div>
                    <div class="field"><label>{{ __('Transfer date') }}</label><input name="paid_on" type="date" required value="{{ old('paid_on', now(Display::TIMEZONE)->toDateString()) }}"></div>
                </div>
                <div class="field"><label>{{ __('Note') }}</label><input name="note" value="{{ old('note') }}"></div>
                <button class="btn" type="submit">@include('admin._icon', ['name' => 'check']) {{ __('Record payout') }}</button>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><h3>{{ __('Manual adjustment') }}</h3></div>
        <div class="card-body">
            <p class="muted small" style="margin-top:0">{{ __('For corrections only, e.g. a refund made outside the system. Every adjustment is logged.') }}</p>
            <form method="POST" action="{{ route('admin.settlements.adjustments.store', $client) }}">
                @csrf
                <div class="grid-2">
                    <div class="field"><label>{{ __('Type') }}</label><select name="direction"><option value="credit">{{ __('Credit (we owe more)') }}</option><option value="debit">{{ __('Debit (we owe less)') }}</option></select></div>
                    <div class="field"><label>{{ __('Amount') }}</label><input name="amount" type="number" min="1" required dir="ltr"></div>
                    <div class="field"><label>{{ __('Unit') }}</label><select name="unit"><option value="irr">{{ __('IRR') }}</option><option value="toman">{{ __('IRT') }}</option></select></div>
                </div>
                <div class="field"><label>{{ __('Reason') }}</label><input name="description" required maxlength="500"></div>
                <button class="btn secondary" type="submit">{{ __('Record adjustment') }}</button>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-head"><h3>{{ __('Ledger') }}</h3>
        <form class="actions" method="GET" style="align-items:center">
            <input type="date" name="from" value="{{ request('from') }}" style="width:auto"><input type="date" name="to" value="{{ request('to') }}" style="width:auto">
            <button class="btn secondary sm">{{ __('Filter') }}</button>
        </form>
    </div>
    <div class="card-body flush"><div class="table-wrap">
    <table>
        <thead><tr><th>{{ __('Time') }}</th><th>{{ __('Type') }}</th><th>{{ __('Description') }}</th><th class="num">{{ __('Amount (Rial)') }}</th><th>{{ __('Payable from') }}</th></tr></thead>
        <tbody>
        @forelse($entries as $e)
            <tr>
                <td class="small ltr" style="white-space:nowrap">{{ Display::date($e->created_at) }}</td>
                <td><span class="badge {{ ['payment' => 'b-success', 'commission' => 'b-warning', 'payout' => 'b-info', 'adjustment' => 'b-muted'][$e->type] ?? '' }}">{{ __('ledger.'.$e->type) }}</span></td>
                <td>
                    @if($e->payment)<a class="mono ltr small" href="{{ route('admin.payments.show', $e->payment) }}">{{ $e->payment->public_id }}</a> <span class="muted small ltr">{{ $e->payment->order_id }}</span>
                    @elseif($e->payout)<span class="small">{{ __('Bank reference') }}: <span class="mono ltr">{{ $e->payout->bank_reference }}</span></span>@if($e->payout->note) <span class="muted small">— {{ $e->payout->note }}</span>@endif
                    @else<span class="small">{{ $e->description }}</span>@endif
                </td>
                <td class="num {{ $e->amount_irr < 0 ? 'neg' : 'pos' }}"><span class="ltr">{{ $e->amount_irr < 0 ? '−' : '+' }}{{ number_format(abs($e->amount_irr)) }}</span></td>
                <td class="small ltr">{{ Display::date($e->available_at) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty">{{ __('No ledger entries yet.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
    </div></div>
    {{ $entries->links('admin.pagination') }}
</div>

<div class="card">
    <div class="card-head"><h3>{{ __('Payouts') }}</h3></div>
    <div class="card-body flush"><div class="table-wrap">
    <table>
        <thead><tr><th>{{ __('Transfer date') }}</th><th class="num">{{ __('Amount (Rial)') }}</th><th>{{ __('Bank reference') }}</th><th>{{ __('IBAN (Sheba)') }}</th><th>{{ __('Recorded by') }}</th><th>{{ __('Note') }}</th></tr></thead>
        <tbody>
        @forelse($payouts as $po)
            <tr>
                <td class="ltr">{{ Display::date($po->paid_on, false) }}</td><td class="num">{{ number_format($po->amount_irr) }}</td>
                <td class="mono ltr">{{ $po->bank_reference }}</td><td class="mono ltr small">{{ $po->iban }}</td>
                <td class="small">{{ $po->creator?->email }}</td><td class="small">{{ $po->note }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">{{ __('No payouts yet.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
    </div></div>
</div>
@endsection
