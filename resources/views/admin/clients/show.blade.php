@extends('admin.layout')
@section('title', $client->name)
@section('content')
<div class="card">
    <h2>{{ $client->name }} <span class="badge s-{{ $client->status->value }}">{{ $client->status->value }}</span></h2>
    <table>
        <tr><th>Client ID</th><td>{{ $client->public_id }}</td><th>Slug</th><td>{{ $client->slug }}</td></tr>
        <tr><th>Webhook URL</th><td>{{ $client->webhook_url ?: '-' }}</td><th>Return URL</th><td>{{ $client->return_url ?: '-' }}</td></tr>
    </table>
    <p>
        <a class="btn secondary" href="{{ route('admin.clients.edit', $client) }}">Edit</a>
        <form class="inline" method="POST" action="{{ route('admin.clients.toggle', $client) }}">@csrf<button class="{{ $client->isActive() ? 'danger' : '' }}">{{ $client->isActive() ? 'Disable' : 'Enable' }}</button></form>
        <form class="inline" method="POST" action="{{ route('admin.clients.webhook-secret', $client) }}">@csrf<button class="secondary">Rotate webhook secret</button></form>
        <a class="btn secondary" href="{{ route('admin.payments.index', ['client' => $client->public_id]) }}">Payments</a>
    </p>
</div>

<div class="card">
    <h3>API credentials</h3>
    <p class="muted">Rotation: issue a new credential, deploy it on the client site, then revoke the old one.</p>
    <table>
        <tr><th>Key ID (X-Client-Id)</th><th>Status</th><th>Last used</th><th>Created</th><th></th></tr>
        @foreach($client->credentials as $cred)
            <tr>
                <td class="secret">{{ $cred->key_id }}</td>
                <td><span class="badge s-{{ $cred->status->value }}">{{ $cred->status->value }}</span></td>
                <td>{{ $cred->last_used_at ?? 'never' }}</td><td>{{ $cred->created_at }}</td>
                <td>@if($cred->isActive())<form class="inline" method="POST" action="{{ route('admin.clients.credentials.revoke', [$client, $cred]) }}">@csrf<button class="danger">Revoke</button></form>@endif</td>
            </tr>
        @endforeach
    </table>
    <form method="POST" action="{{ route('admin.clients.credentials.issue', $client) }}" style="margin-top:12px">@csrf<button>Issue new credential</button></form>
</div>

<div class="card">
    <h3>Merchants</h3>
    <table>
        <tr><th>Name</th><th>Merchant ID</th><th>Provider</th><th>Status</th><th>Default</th><th></th></tr>
        @foreach($client->merchants as $m)
            <tr>
                <td>{{ $m->name }}</td><td>{{ $m->public_id }}</td><td>{{ $m->provider->code }}</td>
                <td><span class="badge s-{{ $m->status->value }}">{{ $m->status->value }}</span></td>
                <td>{{ $m->is_default ? 'yes' : '' }}</td>
                <td>
                    <a href="{{ route('admin.merchants.edit', $m) }}">Edit</a>
                    <form class="inline" method="POST" action="{{ route('admin.merchants.toggle', $m) }}">@csrf<button class="secondary">{{ $m->isActive() ? 'Disable' : 'Enable' }}</button></form>
                    @unless($m->is_default)<form class="inline" method="POST" action="{{ route('admin.merchants.default', $m) }}">@csrf<button class="secondary">Make default</button></form>@endunless
                    <form class="inline" method="POST" action="{{ route('admin.merchants.test', $m) }}">@csrf<button class="secondary">Test credentials</button></form>
                </td>
            </tr>
        @endforeach
    </table>
    <p><a class="btn" href="{{ route('admin.merchants.create', ['client' => $client->public_id]) }}">Add merchant</a></p>
</div>
@endsection
