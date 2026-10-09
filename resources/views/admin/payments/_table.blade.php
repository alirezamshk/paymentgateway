@php use App\Support\Display; @endphp
<div class="table-wrap">
<table>
    <thead><tr><th>{{ __('Payment') }}</th><th>{{ __('Client') }}</th><th>{{ __('Payer') }}</th><th class="num">{{ __('Amount') }}</th><th>{{ __('Status') }}</th><th>{{ __('Card') }}</th><th>{{ __('Reference') }}</th><th>{{ __('Created') }}</th></tr></thead>
    <tbody>
    @forelse($payments as $p)
        <tr>
            <td><a class="mono ltr" href="{{ route('admin.payments.show', $p) }}">{{ \Illuminate\Support\Str::limit($p->public_id, 18) }}</a><div class="muted small ltr">{{ \Illuminate\Support\Str::limit($p->order_id, 24) }}</div>@if($p->is_test)<span class="badge b-warning">{{ __('Admin test') }}</span>@endif</td>
            <td>{{ $p->client?->name }}<div class="muted small">{{ $p->provider?->name }}</div></td>
            <td>@if($p->customer_name || $p->customer_username){{ $p->customer_name ?: $p->customer_username }}@endif<div class="muted small ltr">{{ $p->customer_mobile }}</div></td>
            <td class="num">{{ number_format($p->amount) }} <span class="muted small">{{ __($p->currency->value) }}</span></td>
            <td>@include('admin._status', ['status' => $p->status->value])</td>
            <td class="mono ltr">{{ $p->card_mask }}</td>
            <td class="mono ltr">{{ $p->reference_number }}</td>
            <td class="small ltr" style="white-space:nowrap">{{ Display::date($p->created_at) }}</td>
        </tr>
    @empty
        <tr><td colspan="8" class="empty">{{ __('No payments.') }}</td></tr>
    @endforelse
    </tbody>
</table>
</div>
