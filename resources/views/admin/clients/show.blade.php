@extends('admin.layout')
@php use App\Support\Display; @endphp
@section('title', $client->name)
@section('actions')
    <a class="btn secondary" href="{{ route('admin.settlements.show', $client) }}">@include('admin._icon', ['name' => 'settlements']) {{ __('Settlement') }}</a>
    <a class="btn secondary" href="{{ route('admin.payments.index', ['client' => $client->public_id]) }}">@include('admin._icon', ['name' => 'payments']) {{ __('Payments') }}</a>
    <a class="btn" href="{{ route('admin.clients.edit', $client) }}">{{ __('Edit') }}</a>
@endsection
@section('content')
<div class="card">
    <div class="card-head"><h2>{{ $client->name }}</h2>@include('admin._status', ['status' => $client->status->value])
        <div class="actions">
            <form class="inline" method="POST" action="{{ route('admin.clients.toggle', $client) }}">@csrf<button class="btn sm {{ $client->isActive() ? 'danger' : '' }}">{{ $client->isActive() ? __('Disable') : __('Enable') }}</button></form>
        </div>
    </div>
    <div class="card-body">
        <dl class="kv">
            <div><dt>{{ __('Client ID') }}</dt><dd class="mono ltr">{{ $client->public_id }}</dd></div>
            <div><dt>{{ __('Slug') }}</dt><dd class="mono ltr">{{ $client->slug }}</dd></div>
            <div><dt>{{ __('Webhook URL') }}</dt><dd class="ltr small">{{ $client->webhook_url ?: '—' }}</dd></div>
            <div><dt>{{ __('Return URL') }}</dt><dd class="ltr small">{{ $client->return_url ?: '—' }}</dd></div>
            <div><dt>{{ __('Commission') }}</dt><dd>@include('admin.settlements._commission', ['client' => $client])</dd></div>
            <div><dt>{{ __('IBAN (Sheba)') }}</dt><dd class="mono ltr">{{ $client->iban ?: '—' }}</dd></div>
        </dl>
    </div>
</div>

<div class="card">
    <div class="card-head"><h3>{{ __('API credentials') }}</h3>
        <div class="actions">
            <form class="inline" method="POST" action="{{ route('admin.clients.webhook-secret', $client) }}">@csrf<button class="btn secondary sm">@include('admin._icon', ['name' => 'refresh']) {{ __('Rotate webhook secret') }}</button></form>
            <form class="inline" method="POST" action="{{ route('admin.clients.credentials.issue', $client) }}">@csrf<button class="btn sm">@include('admin._icon', ['name' => 'key']) {{ __('Issue new credential') }}</button></form>
        </div>
    </div>
    <div class="card-body flush"><div class="table-wrap">
    <table>
        <thead><tr><th>{{ __('Key ID (X-Client-Id)') }}</th><th>{{ __('Status') }}</th><th>{{ __('Last used') }}</th><th>{{ __('Created') }}</th><th></th></tr></thead>
        <tbody>
        @foreach($client->credentials as $cred)
            <tr>
                <td class="mono ltr">{{ $cred->key_id }}</td>
                <td>@include('admin._status', ['status' => $cred->status->value])</td>
                <td class="small ltr">{{ $cred->last_used_at ? Display::date($cred->last_used_at) : __('never') }}</td><td class="small ltr">{{ Display::date($cred->created_at) }}</td>
                <td class="num">@if($cred->isActive())<form class="inline" method="POST" action="{{ route('admin.clients.credentials.revoke', [$client, $cred]) }}">@csrf<button class="btn danger sm">{{ __('Revoke') }}</button></form>@endif</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div></div>
    <div class="card-body muted small">{{ __('Rotation: issue a new credential, deploy it on the client site, then revoke the old one.') }}</div>
</div>

<div class="card">
    <div class="card-head"><h3>{{ __('Merchants') }}</h3><div class="actions"><a class="btn sm" href="{{ route('admin.merchants.create', ['client' => $client->public_id]) }}">@include('admin._icon', ['name' => 'plus']) {{ __('Add merchant') }}</a></div></div>
    <div class="card-body flush"><div class="table-wrap">
    <table>
        <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Provider') }}</th><th>{{ __('Status') }}</th><th>{{ __('Default') }}</th><th></th></tr></thead>
        <tbody>
        @forelse($client->merchants as $m)
            <tr>
                <td><strong>{{ $m->name }}</strong><div class="muted small mono ltr">{{ $m->public_id }}</div></td><td>{{ $m->provider->name }}</td>
                <td>@include('admin._status', ['status' => $m->status->value])</td>
                <td>@if($m->is_default)<span class="badge b-info">{{ __('Default') }}</span>@endif</td>
                <td class="num" style="white-space:nowrap">
                    <a class="btn ghost sm" href="{{ route('admin.merchants.edit', $m) }}">{{ __('Edit') }}</a>
                    <form class="inline" method="POST" action="{{ route('admin.merchants.test', $m) }}">@csrf<button class="btn secondary sm">{{ __('Test credentials') }}</button></form>
                    @include('admin.merchants._test_payment', ['merchant' => $m])
                    @unless($m->is_default)<form class="inline" method="POST" action="{{ route('admin.merchants.default', $m) }}">@csrf<button class="btn secondary sm">{{ __('Make default') }}</button></form>@endunless
                    <form class="inline" method="POST" action="{{ route('admin.merchants.toggle', $m) }}">@csrf<button class="btn secondary sm">{{ $m->isActive() ? __('Disable') : __('Enable') }}</button></form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty">{{ __('No merchants yet.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
    </div></div>
</div>
@endsection
