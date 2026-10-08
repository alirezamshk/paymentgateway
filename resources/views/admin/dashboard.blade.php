@extends('admin.layout')
@section('title', __('Dashboard'))
@section('content')
<div class="grid">
    <div class="card"><div class="muted">{{ __('Clients') }}</div><div class="stat">{{ $clients }}</div></div>
    <div class="card"><div class="muted">{{ __('Merchants') }}</div><div class="stat">{{ $merchants }}</div></div>
    <div class="card"><div class="muted">{{ __('Paid (24h)') }}</div><div class="stat">{{ $paid24h }}</div></div>
    <div class="card"><div class="muted">{{ __('Failed (24h)') }}</div><div class="stat">{{ $failed24h }}</div></div>
    <div class="card"><div class="muted">{{ __('Awaiting verification') }}</div><div class="stat">{{ $stuck }}</div></div>
    <div class="card"><div class="muted">{{ __('Failed webhooks') }}</div><div class="stat">{{ $webhooksFailed }}</div></div>
</div>
<div class="card">
    <h3>{{ __('Recent payments') }}</h3>
    @include('admin.payments._table', ['payments' => $recent])
</div>
@endsection
