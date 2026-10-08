@extends('admin.layout')
@section('title', __('Dashboard'))
@section('content')
<div class="stats">
    <div class="stat"><div class="ic success">@include('admin._icon', ['name' => 'check', 'size' => 20])</div><div><div class="label">{{ __('Paid (24h)') }}</div><div class="value">{{ number_format($paid24h) }}</div><div class="sub">{{ \App\Support\Display::rial($paidAmount24h) }}</div></div></div>
    <div class="stat"><div class="ic danger">@include('admin._icon', ['name' => 'alert', 'size' => 20])</div><div><div class="label">{{ __('Failed (24h)') }}</div><div class="value">{{ number_format($failed24h) }}</div></div></div>
    <div class="stat"><div class="ic warning">@include('admin._icon', ['name' => 'clock', 'size' => 20])</div><div><div class="label">{{ __('Awaiting verification') }}</div><div class="value">{{ number_format($stuck) }}</div></div></div>
    <div class="stat"><div class="ic">@include('admin._icon', ['name' => 'settlements', 'size' => 20])</div><div><div class="label">{{ __('Owed to clients') }}</div><div class="value">{{ \App\Support\Display::rial($owed) }}</div><div class="sub">{{ \App\Support\Display::toman($owed) }}</div></div></div>
    <div class="stat"><div class="ic">@include('admin._icon', ['name' => 'sites', 'size' => 20])</div><div><div class="label">{{ __('Clients') }} / {{ __('Merchants') }}</div><div class="value">{{ $clients }} / {{ $merchants }}</div></div></div>
    <div class="stat"><div class="ic {{ $webhooksFailed ? 'danger' : 'success' }}">@include('admin._icon', ['name' => 'webhooks', 'size' => 20])</div><div><div class="label">{{ __('Failed webhooks') }}</div><div class="value">{{ number_format($webhooksFailed) }}</div></div></div>
</div>
<div class="card">
    <div class="card-head"><h2>{{ __('Recent payments') }}</h2><div class="actions"><a class="btn secondary sm" href="{{ route('admin.payments.index') }}">{{ __('All payments') }}</a></div></div>
    <div class="card-body flush">@include('admin.payments._table', ['payments' => $recent])</div>
</div>
@endsection
