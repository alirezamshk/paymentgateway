@extends('admin.layout')
@section('title', $payment->public_id)
@section('content')
<div class="card">
    <h2>{{ $payment->public_id }} <span class="badge s-{{ $payment->status->value }}">{{ $payment->status->value }}</span></h2>
    <table>
        <tr><th>Client</th><td><a href="{{ route('admin.clients.show', $payment->client) }}">{{ $payment->client->name }}</a></td><th>Order ID</th><td>{{ $payment->order_id }}</td></tr>
        <tr><th>Merchant</th><td>{{ $payment->merchant->name }} ({{ $payment->merchant->public_id }})</td><th>Provider</th><td>{{ $payment->provider->code }}</td></tr>
        <tr><th>Amount</th><td>{{ number_format($payment->amount) }} {{ $payment->currency->value }}</td><th>Description</th><td>{{ $payment->description }}</td></tr>
        <tr><th>Reference</th><td>{{ $payment->reference_number }}</td><th>Trace</th><td>{{ $payment->trace_number }}</td></tr>
        <tr><th>Card</th><td>{{ $payment->card_mask }}</td><th>Attempts</th><td>{{ $payment->attempts_count }}</td></tr>
        <tr><th>Created</th><td>{{ $payment->created_at }}</td><th>Paid</th><td>{{ $payment->paid_at }}</td></tr>
        <tr><th>Expires</th><td>{{ $payment->expires_at }}</td><th>Settled</th><td>{{ $payment->settled_at }}</td></tr>
        <tr><th>Return URL</th><td colspan="3">{{ $payment->return_url }}</td></tr>
    </table>
    @if(in_array($payment->status->value, ['callback_received', 'verifying']))
        <form method="POST" action="{{ route('admin.payments.verify', $payment) }}" style="margin-top:12px">@csrf<button>Re-run PSP verification</button></form>
    @endif
</div>

<div class="card">
    <h3>Attempts</h3>
    <table>
        <tr><th>#</th><th>Provider</th><th>Status</th><th>PSP invoice</th><th>Authority / token</th><th>Error</th><th>Created</th></tr>
        @foreach($payment->attempts as $a)
            <tr>
                <td>{{ $a->attempt_number }}</td><td>{{ $a->provider->code }}</td>
                <td><span class="badge">{{ $a->status->value }}</span></td>
                <td>{{ $a->psp_invoice_id }}</td>
                <td><span class="secret">{{ \Illuminate\Support\Str::limit($a->authority ?? $a->token, 40) }}</span></td>
                <td>{{ $a->error_code }} {{ $a->error_message }}</td><td>{{ $a->created_at }}</td>
            </tr>
            <tr><td></td><td colspan="6">
                <details><summary>PSP payloads (masked)</summary>
                    <pre>{{ json_encode(['request' => $a->request_payload, 'response' => $a->response_payload, 'callback' => $a->callback_payload, 'verify' => $a->verify_payload], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                </details>
            </td></tr>
        @endforeach
    </table>
</div>

<div class="card">
    <h3>Timeline</h3>
    <table>
        <tr><th>Time</th><th>Event</th><th>Transition</th><th>Source</th><th>Request</th><th>Details</th></tr>
        @foreach($payment->events as $e)
            <tr>
                <td>{{ $e->created_at }}</td><td>{{ $e->event }}</td>
                <td>@if($e->new_status){{ $e->old_status ?? '-' }} &rarr; {{ $e->new_status }}@endif</td>
                <td>{{ $e->source }}</td><td class="muted">{{ $e->request_id }}</td>
                <td>@if($e->metadata)<pre>{{ json_encode($e->metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>@endif</td>
            </tr>
        @endforeach
    </table>
</div>

<div class="card">
    <h3>Webhooks</h3>
    @include('admin.webhooks._table', ['deliveries' => $payment->webhookDeliveries])
</div>
@endsection
