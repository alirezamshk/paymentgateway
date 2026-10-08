@extends('admin.layout')
@section('title', $client->name)
@section('content')
<div class="card">
    <h2>{{ $client->name }} <span class="badge s-{{ $client->status->value }}">{{ __($client->status->value) }}</span></h2>
    <table>
        <tr><th>{{ __('Client ID') }}</th><td dir="ltr">{{ $client->public_id }}</td><th>{{ __('Slug') }}</th><td dir="ltr">{{ $client->slug }}</td></tr>
        <tr><th>{{ __('Webhook URL') }}</th><td dir="ltr">{{ $client->webhook_url ?: '-' }}</td><th>{{ __('Return URL') }}</th><td dir="ltr">{{ $client->return_url ?: '-' }}</td></tr>
    </table>
    <p>
        <a class="btn secondary" href="{{ route('admin.clients.edit', $client) }}">{{ __('Edit') }}</a>
        <form class="inline" method="POST" action="{{ route('admin.clients.toggle', $client) }}">@csrf<button class="{{ $client->isActive() ? 'danger' : '' }}">{{ $client->isActive() ? __('Disable') : __('Enable') }}</button></form>
        <form class="inline" method="POST" action="{{ route('admin.clients.webhook-secret', $client) }}">@csrf<button class="secondary">{{ __('Rotate webhook secret') }}</button></form>
        <a class="btn secondary" href="{{ route('admin.payments.index', ['client' => $client->public_id]) }}">{{ __('Payments') }}</a>
    </p>
</div>

<div class="card">
    <h3>{{ __('API credentials') }}</h3>
    <p class="muted">{{ __('Rotation: issue a new credential, deploy it on the client site, then revoke the old one.') }}</p>
    <table>
        <tr><th>{{ __('Key ID (X-Client-Id)') }}</th><th>{{ __('Status') }}</th><th>{{ __('Last used') }}</th><th>{{ __('Created') }}</th><th></th></tr>
        @foreach($client->credentials as $cred)
            <tr>
                <td class="secret">{{ $cred->key_id }}</td>
                <td><span class="badge s-{{ $cred->status->value }}">{{ __($cred->status->value) }}</span></td>
                <td dir="ltr">{{ $cred->last_used_at ?? __('never') }}</td><td dir="ltr">{{ $cred->created_at }}</td>
                <td>@if($cred->isActive())<form class="inline" method="POST" action="{{ route('admin.clients.credentials.revoke', [$client, $cred]) }}">@csrf<button class="danger">{{ __('Revoke') }}</button></form>@endif</td>
            </tr>
        @endforeach
    </table>
    <form method="POST" action="{{ route('admin.clients.credentials.issue', $client) }}" style="margin-top:12px">@csrf<button>{{ __('Issue new credential') }}</button></form>
</div>

<div class="card">
    <h3>{{ __('Merchants') }}</h3>
    <table>
        <tr><th>{{ __('Name') }}</th><th>{{ __('Merchant ID') }}</th><th>{{ __('Provider') }}</th><th>{{ __('Status') }}</th><th>{{ __('Default') }}</th><th></th></tr>
        @foreach($client->merchants as $m)
            <tr>
                <td>{{ $m->name }}</td><td dir="ltr">{{ $m->public_id }}</td><td>{{ $m->provider->code }}</td>
                <td><span class="badge s-{{ $m->status->value }}">{{ __($m->status->value) }}</span></td>
                <td>{{ $m->is_default ? __('yes') : '' }}</td>
                <td>
                    <a href="{{ route('admin.merchants.edit', $m) }}">{{ __('Edit') }}</a>
                    <form class="inline" method="POST" action="{{ route('admin.merchants.toggle', $m) }}">@csrf<button class="secondary">{{ $m->isActive() ? __('Disable') : __('Enable') }}</button></form>
                    @unless($m->is_default)<form class="inline" method="POST" action="{{ route('admin.merchants.default', $m) }}">@csrf<button class="secondary">{{ __('Make default') }}</button></form>@endunless
                    <form class="inline" method="POST" action="{{ route('admin.merchants.test', $m) }}">@csrf<button class="secondary">{{ __('Test credentials') }}</button></form>
                </td>
            </tr>
        @endforeach
    </table>
    <p><a class="btn" href="{{ route('admin.merchants.create', ['client' => $client->public_id]) }}">{{ __('Add merchant') }}</a></p>
</div>
@endsection
