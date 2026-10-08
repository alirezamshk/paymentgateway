@extends('admin.layout')
@section('title', $payment->public_id)
@section('content')
<div class="card">
    <h2><span dir="ltr">{{ $payment->public_id }}</span> <span class="badge s-{{ $payment->status->value }}">{{ __($payment->status->value) }}</span></h2>
    <table>
        <tr><th>{{ __('Client') }}</th><td><a href="{{ route('admin.clients.show', $payment->client) }}">{{ $payment->client->name }}</a></td><th>{{ __('Order ID') }}</th><td dir="ltr">{{ $payment->order_id }}</td></tr>
        <tr><th>{{ __('Merchant') }}</th><td>{{ $payment->merchant->name }} <bdi dir="ltr">({{ $payment->merchant->public_id }})</bdi></td><th>{{ __('Provider') }}</th><td>{{ $payment->provider->code }}</td></tr>
        <tr><th>{{ __('Amount') }}</th><td>{{ number_format($payment->amount) }} {{ __($payment->currency->value) }}</td><th>{{ __('Description') }}</th><td>{{ $payment->description }}</td></tr>
        <tr><th>{{ __('Reference') }}</th><td dir="ltr">{{ $payment->reference_number }}</td><th>{{ __('Trace') }}</th><td dir="ltr">{{ $payment->trace_number }}</td></tr>
        <tr><th>{{ __('Card') }}</th><td dir="ltr">{{ $payment->card_mask }}</td><th>{{ __('Attempts') }}</th><td>{{ $payment->attempts_count }}</td></tr>
        <tr><th>{{ __('Created') }}</th><td dir="ltr">{{ $payment->created_at }}</td><th>{{ __('Paid') }}</th><td dir="ltr">{{ $payment->paid_at }}</td></tr>
        <tr><th>{{ __('Expires') }}</th><td dir="ltr">{{ $payment->expires_at }}</td><th>{{ __('Settled') }}</th><td dir="ltr">{{ $payment->settled_at }}</td></tr>
        <tr><th>{{ __('Return URL') }}</th><td colspan="3" dir="ltr">{{ $payment->return_url }}</td></tr>
    </table>
    @if(in_array($payment->status->value, ['callback_received', 'verifying']))
        <form method="POST" action="{{ route('admin.payments.verify', $payment) }}" style="margin-top:12px">@csrf<button>{{ __('Re-run PSP verification') }}</button></form>
    @endif
</div>

<div class="card">
    <h3>{{ __('Attempts') }}</h3>
    <table>
        <tr><th>#</th><th>{{ __('Provider') }}</th><th>{{ __('Status') }}</th><th>{{ __('PSP invoice') }}</th><th>{{ __('Authority / token') }}</th><th>{{ __('Error') }}</th><th>{{ __('Created') }}</th></tr>
        @foreach($payment->attempts as $a)
            <tr>
                <td>{{ $a->attempt_number }}</td><td>{{ $a->provider->code }}</td>
                <td><span class="badge">{{ __('attempt.'.$a->status->value) }}</span></td>
                <td dir="ltr">{{ $a->psp_invoice_id }}</td>
                <td><span class="secret">{{ \Illuminate\Support\Str::limit($a->authority ?? $a->token, 40) }}</span></td>
                <td dir="ltr">{{ $a->error_code }} {{ $a->error_message }}</td><td dir="ltr">{{ $a->created_at }}</td>
            </tr>
            <tr><td></td><td colspan="6">
                <details><summary>{{ __('PSP payloads (masked)') }}</summary>
                    <pre>{{ json_encode(['request' => $a->request_payload, 'response' => $a->response_payload, 'callback' => $a->callback_payload, 'verify' => $a->verify_payload], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                </details>
            </td></tr>
        @endforeach
    </table>
</div>

<div class="card">
    <h3>{{ __('Timeline') }}</h3>
    <table>
        <tr><th>{{ __('Time') }}</th><th>{{ __('Event') }}</th><th>{{ __('Transition') }}</th><th>{{ __('Source') }}</th><th>{{ __('Request') }}</th><th>{{ __('Details') }}</th></tr>
        @foreach($payment->events as $e)
            <tr>
                <td dir="ltr">{{ $e->created_at }}</td><td dir="ltr">{{ $e->event }}</td>
                <td>@if($e->new_status){{ $e->old_status ? __($e->old_status) : '-' }} {{ app()->getLocale() === 'fa' ? '←' : '→' }} {{ __($e->new_status) }}@endif</td>
                <td>{{ __('source.'.$e->source) }}</td><td class="muted" dir="ltr">{{ $e->request_id }}</td>
                <td>@if($e->metadata)<pre>{{ json_encode($e->metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>@endif</td>
            </tr>
        @endforeach
    </table>
</div>

<div class="card">
    <h3>{{ __('Webhooks') }}</h3>
    @include('admin.webhooks._table', ['deliveries' => $payment->webhookDeliveries])
</div>
@endsection
