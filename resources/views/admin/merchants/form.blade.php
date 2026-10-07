@extends('admin.layout')
@section('title', $merchant->exists ? 'Edit merchant' : 'New merchant')
@section('content')
<div class="card" style="max-width:720px">
    <h2>{{ $merchant->exists ? 'Edit '.$merchant->name : 'New merchant' }}</h2>
    <form method="POST" action="{{ $merchant->exists ? route('admin.merchants.update', $merchant) : route('admin.merchants.store') }}" autocomplete="off">
        @csrf
        @if($merchant->exists) @method('PUT') @endif
        @unless($merchant->exists)
            <label>Client</label>
            <select name="client" required>
                @foreach($clients as $c)<option value="{{ $c->public_id }}" @selected(old('client', $selectedClient) === $c->public_id)>{{ $c->name }}</option>@endforeach
            </select>
        @else
            <p>Client: <strong>{{ $merchant->client->name }}</strong></p>
        @endunless
        <label>Name</label><input name="name" value="{{ old('name', $merchant->name) }}" required style="width:100%">
        <label>Provider</label>
        <select name="provider" required>
            @foreach($providers as $p)<option value="{{ $p->code }}" @selected(old('provider', $merchant->provider?->code) === $p->code)>{{ $p->name }} ({{ $p->status->value }})</option>@endforeach
        </select>

        <h3>Credentials</h3>
        <p class="muted">Stored encrypted. Existing secret values are never displayed; leave a field empty to keep its current value.</p>
        <table>
            <tr><th>Provider</th><th>Required</th></tr>
            @foreach($requirements as $code => $fields)
                <tr><td>{{ $code }}</td><td>@forelse($fields as $key => $label)<div><code>{{ $key }}</code> - {{ $label }}</div>@empty <span class="muted">none</span> @endforelse</td></tr>
            @endforeach
        </table>
        @php $configured = $merchant->exists ? array_keys($merchant->credentials()) : []; @endphp
        @foreach(['merchant_identifier' => 'Merchant identifier', 'terminal_identifier' => 'Terminal identifier', 'username' => 'Username', 'password' => 'Password', 'api_key' => 'API key'] as $key => $label)
            <label>{{ $label }} @if(in_array($key, $configured))<span class="badge s-active">configured</span>@endif</label>
            <input type="{{ in_array($key, ['password', 'api_key']) ? 'password' : 'text' }}" name="credentials[{{ $key }}]" value="" style="width:100%" autocomplete="new-password">
        @endforeach
        <label>Extra provider config (JSON object, encrypted)</label>
        <textarea name="extra_config" placeholder='{"key": "value"}'></textarea>
        <label><input type="checkbox" name="is_default" value="1" @checked(old('is_default', $merchant->is_default))> Default merchant for this client</label>
        <p><button type="submit">Save</button></p>
    </form>
</div>
@endsection
