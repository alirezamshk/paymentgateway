@extends('admin.layout')
@section('title', $delivery->public_id)
@section('content')
<div class="card">
    <h2>{{ $delivery->public_id }} <span class="badge s-{{ $delivery->status->value }}">{{ $delivery->status->value }}</span></h2>
    <table>
        <tr><th>Client</th><td>{{ $delivery->client->name }}</td><th>Payment</th><td><a href="{{ route('admin.payments.show', $delivery->payment) }}">{{ $delivery->payment->public_id }}</a></td></tr>
        <tr><th>Event</th><td>{{ $delivery->event }}</td><th>Endpoint</th><td>{{ $delivery->endpoint }}</td></tr>
        <tr><th>Attempts</th><td>{{ $delivery->attempt }}</td><th>HTTP status</th><td>{{ $delivery->http_status }}</td></tr>
        <tr><th>Next retry</th><td>{{ $delivery->next_retry_at }}</td><th>Delivered</th><td>{{ $delivery->delivered_at }}</td></tr>
        <tr><th>Last error</th><td colspan="3">{{ $delivery->last_error }}</td></tr>
        <tr><th>Request id</th><td colspan="3">{{ $delivery->request_id }}</td></tr>
    </table>
    @if($delivery->status->value !== 'processing')
        <form method="POST" action="{{ route('admin.webhooks.retry', $delivery) }}" style="margin-top:12px">@csrf<button>Retry now</button></form>
    @endif
</div>
<div class="card"><h3>Payload</h3><pre>{{ json_encode($delivery->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></div>
<div class="card"><h3>Last response body</h3><pre>{{ $delivery->response_body }}</pre></div>
@endsection
