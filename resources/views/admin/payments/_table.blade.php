<table>
    <tr><th>{{ __('Payment') }}</th><th>{{ __('Client') }}</th><th>{{ __('Order') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Provider') }}</th><th>{{ __('Status') }}</th><th>{{ __('Reference') }}</th><th>{{ __('Created') }}</th></tr>
    @forelse($payments as $p)
        <tr>
            <td><a href="{{ route('admin.payments.show', $p) }}" dir="ltr">{{ $p->public_id }}</a></td>
            <td>{{ $p->client?->name }}</td>
            <td dir="ltr">{{ $p->order_id }}</td>
            <td>{{ number_format($p->amount) }} {{ __($p->currency->value) }}</td>
            <td>{{ $p->provider?->code }}</td>
            <td><span class="badge s-{{ $p->status->value }}">{{ __($p->status->value) }}</span></td>
            <td dir="ltr">{{ $p->reference_number }}</td>
            <td dir="ltr">{{ $p->created_at }}</td>
        </tr>
    @empty
        <tr><td colspan="8" class="muted">{{ __('No payments.') }}</td></tr>
    @endforelse
</table>
