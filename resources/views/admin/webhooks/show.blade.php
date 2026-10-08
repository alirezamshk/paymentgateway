@extends('admin.layout')
@php use App\Support\Display; @endphp
@section('title', __('Webhook delivery'))
@section('actions')
    @if($delivery->status->value !== 'processing')
        <form class="inline" method="POST" action="{{ route('admin.webhooks.retry', $delivery) }}">@csrf<button class="btn">@include('admin._icon', ['name' => 'refresh']) {{ __('Retry now') }}</button></form>
    @endif
@endsection
@section('content')
<div class="card">
    <div class="card-head"><h2 class="mono ltr">{{ $delivery->public_id }}</h2>@include('admin._status', ['status' => $delivery->status->value, 'prefix' => 'webhook.'])</div>
    <div class="card-body">
        <dl class="kv">
            <div><dt>{{ __('Client') }}</dt><dd>{{ $delivery->client->name }}</dd></div>
            <div><dt>{{ __('Payment') }}</dt><dd><a class="mono ltr" href="{{ route('admin.payments.show', $delivery->payment) }}">{{ $delivery->payment->public_id }}</a></dd></div>
            <div><dt>{{ __('Event') }}</dt><dd class="mono ltr">{{ $delivery->event }}</dd></div>
            <div><dt>{{ __('Attempts') }}</dt><dd>{{ $delivery->attempt }}</dd></div>
            <div><dt>{{ __('HTTP status') }}</dt><dd class="mono">{{ $delivery->http_status ?: '—' }}</dd></div>
            <div><dt>{{ __('Next retry') }}</dt><dd class="ltr">{{ Display::date($delivery->next_retry_at) }}</dd></div>
            <div><dt>{{ __('Delivered') }}</dt><dd class="ltr">{{ Display::date($delivery->delivered_at) }}</dd></div>
            <div><dt>{{ __('Request id') }}</dt><dd class="mono ltr small">{{ $delivery->request_id }}</dd></div>
        </dl>
        <div class="muted small" style="margin-top:12px">{{ __('Endpoint') }}: <span class="ltr">{{ $delivery->endpoint }}</span></div>
        @if($delivery->last_error)<div class="alert err" style="margin-top:12px">@include('admin._icon', ['name' => 'alert'])<div><strong>{{ __('Last error') }}:</strong> <span class="ltr">{{ $delivery->last_error }}</span></div></div>@endif
    </div>
</div>
<div class="card"><div class="card-head"><h3>{{ __('Payload') }}</h3></div><div class="card-body"><pre>{{ json_encode($delivery->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre></div></div>
<div class="card"><div class="card-head"><h3>{{ __('Last response body') }}</h3></div><div class="card-body"><pre>{{ $delivery->response_body ?: '—' }}</pre></div></div>
@endsection
