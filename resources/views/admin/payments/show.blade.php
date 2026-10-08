@extends('admin.layout')
@php use App\Support\Display; @endphp
@section('title', __('Payment details'))
@section('actions')
    @if(in_array($payment->status->value, ['callback_received', 'verifying']))
        <form class="inline" method="POST" action="{{ route('admin.payments.verify', $payment) }}">@csrf<button class="btn">@include('admin._icon', ['name' => 'refresh']) {{ __('Re-run PSP verification') }}</button></form>
    @endif
@endsection
@section('content')
<div class="card">
    <div class="card-head">
        <h2 class="mono ltr">{{ $payment->public_id }}</h2>
        @include('admin._status', ['status' => $payment->status->value])
        <div class="actions"><span class="muted">{{ number_format($payment->amount) }} {{ __($payment->currency->value) }}</span></div>
    </div>
    <div class="card-body">
        <dl class="kv">
            <div><dt>{{ __('Client') }}</dt><dd><a href="{{ route('admin.clients.show', $payment->client) }}">{{ $payment->client->name }}</a></dd></div>
            <div><dt>{{ __('Order ID') }}</dt><dd class="ltr mono">{{ $payment->order_id }}</dd></div>
            <div><dt>{{ __('Merchant') }}</dt><dd>{{ $payment->merchant->name }}</dd></div>
            <div><dt>{{ __('Provider') }}</dt><dd>{{ $payment->provider->name }}</dd></div>
            <div><dt>{{ __('Amount') }}</dt><dd>{{ number_format($payment->amount) }} {{ __($payment->currency->value) }}</dd></div>
            <div><dt>{{ __('Description') }}</dt><dd>{{ $payment->description ?: '—' }}</dd></div>
            <div><dt>{{ __('Payer') }}</dt><dd>{{ $payment->customer_name ?: '—' }} @if($payment->customer_username)<span class="muted ltr">({{ $payment->customer_username }})</span>@endif</dd></div>
            <div><dt>{{ __('Mobile') }}</dt><dd class="ltr">{{ $payment->customer_mobile ?: '—' }}</dd></div>
            <div><dt>{{ __('Card') }}</dt><dd class="ltr mono">{{ $payment->card_mask ?: '—' }}</dd></div>
            <div><dt>{{ __('Reference') }}</dt><dd class="ltr mono">{{ $payment->reference_number ?: '—' }}</dd></div>
            <div><dt>{{ __('Trace') }}</dt><dd class="ltr mono">{{ $payment->trace_number ?: '—' }}</dd></div>
            <div><dt>{{ __('Attempts') }}</dt><dd>{{ $payment->attempts_count }}</dd></div>
            <div><dt>{{ __('Created') }}</dt><dd class="ltr">{{ Display::date($payment->created_at) }}</dd></div>
            <div><dt>{{ __('Paid') }}</dt><dd class="ltr">{{ Display::date($payment->paid_at) }}</dd></div>
            <div><dt>{{ __('Expires') }}</dt><dd class="ltr">{{ Display::date($payment->expires_at) }}</dd></div>
            <div><dt>{{ __('Settled') }}</dt><dd class="ltr">{{ Display::date($payment->settled_at) }}</dd></div>
        </dl>
        <div class="muted small" style="margin-top:12px">{{ __('Return URL') }}: <span class="ltr">{{ $payment->return_url }}</span></div>
        @if($payment->metadata)
            <div style="margin-top:14px">
                <div class="muted small" style="margin-bottom:6px">{{ __('Metadata sent by the client') }}</div>
                <pre>{{ json_encode($payment->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-head"><h3>{{ __('Attempts') }}</h3></div>
    <div class="card-body flush"><div class="table-wrap">
    <table>
        <thead><tr><th>#</th><th>{{ __('Provider') }}</th><th>{{ __('Status') }}</th><th>{{ __('PSP invoice') }}</th><th>{{ __('Error') }}</th><th>{{ __('Created') }}</th><th></th></tr></thead>
        <tbody>
        @foreach($payment->attempts as $a)
            <tr>
                <td>{{ $a->attempt_number }}</td><td>{{ $a->provider->name }}</td>
                <td>@include('admin._status', ['status' => $a->status->value, 'prefix' => 'attempt.'])</td>
                <td class="mono ltr">{{ $a->psp_invoice_id }}</td>
                <td class="small ltr">{{ $a->error_code }} {{ $a->error_message }}</td>
                <td class="small ltr">{{ Display::date($a->created_at) }}</td>
                <td><details><summary>{{ __('PSP payloads (masked)') }}</summary><pre>{{ json_encode(['request' => $a->request_payload, 'response' => $a->response_payload, 'callback' => $a->callback_payload, 'verify' => $a->verify_payload], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></details></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div></div>
</div>

<div class="card">
    <div class="card-head"><h3>{{ __('Timeline') }}</h3></div>
    <ul class="timeline">
        @foreach($payment->events as $e)
            <li>
                <div class="muted small ltr">{{ Display::date($e->created_at) }}:{{ $e->created_at?->format('s') }}</div>
                <div>
                    <span class="ev ltr">{{ $e->event }}</span>
                    @if($e->new_status)<span class="muted"> · {{ $e->old_status ? __($e->old_status) : '—' }} {{ app()->getLocale() === 'fa' ? '←' : '→' }} {{ __($e->new_status) }}</span>@endif
                    <span class="muted small"> · {{ __('source.'.$e->source) }}</span>
                    @if($e->metadata)<details><summary class="small">{{ __('Details') }}</summary><pre>{{ json_encode($e->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></details>@endif
                </div>
            </li>
        @endforeach
    </ul>
</div>

<div class="card">
    <div class="card-head"><h3>{{ __('Webhooks') }}</h3></div>
    <div class="card-body flush">@include('admin.webhooks._table', ['deliveries' => $payment->webhookDeliveries])</div>
</div>
@endsection
