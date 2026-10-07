<table>
    <tr><th>Payment</th><th>Client</th><th>Order</th><th>Amount</th><th>Provider</th><th>Status</th><th>Reference</th><th>Created</th></tr>
    @forelse($payments as $p)
        <tr>
            <td><a href="{{ route('admin.payments.show', $p) }}">{{ $p->public_id }}</a></td>
            <td>{{ $p->client?->name }}</td>
            <td>{{ $p->order_id }}</td>
            <td>{{ number_format($p->amount) }} {{ $p->currency->value }}</td>
            <td>{{ $p->provider?->code }}</td>
            <td><span class="badge s-{{ $p->status->value }}">{{ $p->status->value }}</span></td>
            <td>{{ $p->reference_number }}</td>
            <td>{{ $p->created_at }}</td>
        </tr>
    @empty
        <tr><td colspan="8" class="muted">No payments.</td></tr>
    @endforelse
</table>
