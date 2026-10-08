@extends('admin.layout')
@section('title', $delivery->public_id)
@section('content')
<div class="card">
    <h2><span dir="ltr">{{ $delivery->public_id }}</span> <span class="badge s-{{ $delivery->status->value }}">{{ __('webhook.'.$delivery->status->value) }}</span></h2>
    <table>
        <tr><th>{{ __('Client') }}</th><td>{{ $delivery->client->name }}</td><th>{{ __('Payment') }}</th><td><a href="{{ route('admin.payments.show', $delivery->payment) }}" dir="ltr">{{ $delivery->payment->public_id }}</a></td></tr>
        <tr><th>{{ __('Event') }}</th><td dir="ltr">{{ $delivery->event }}</td><th>{{ __('Endpoint') }}</th><td dir="ltr">{{ $delivery->endpoint }}</td></tr>
        <tr><th>{{ __('Attempts') }}</th><td>{{ $delivery->attempt }}</td><th>{{ __('HTTP status') }}</th><td>{{ $delivery->http_status }}</td></tr>
        <tr><th>{{ __('Next retry') }}</th><td dir="ltr">{{ $delivery->next_retry_at }}</td><th>{{ __('Delivered') }}</th><td dir="ltr">{{ $delivery->delivered_at }}</td></tr>
        <tr><th>{{ __('Last error') }}</th><td colspan="3" dir="ltr">{{ $delivery->last_error }}</td></tr>
        <tr><th>{{ __('Request id') }}</th><td colspan="3" dir="ltr">{{ $delivery->request_id }}</td></tr>
    </table>
    @if($delivery->status->value !== 'processing')
        <form method="POST" action="{{ route('admin.webhooks.retry', $delivery) }}" style="margin-top:12px">@csrf<button>{{ __('Retry now') }}</button></form>
    @endif
</div>
<div class="card"><h3>{{ __('Payload') }}</h3><pre>{{ json_encode($delivery->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></div>
<div class="card"><h3>{{ __('Last response body') }}</h3><pre>{{ $delivery->response_body }}</pre></div>
@endsection
