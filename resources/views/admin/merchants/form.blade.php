@extends('admin.layout')
@section('title', $merchant->exists ? __('Edit merchant') : __('New merchant'))
@section('content')
<form method="POST" action="{{ $merchant->exists ? route('admin.merchants.update', $merchant) : route('admin.merchants.store') }}" autocomplete="off" style="max-width:860px">
    @csrf
    @if($merchant->exists) @method('PUT') @endif
    <div class="card">
        <div class="card-head"><h2>{{ __('Merchant') }}</h2></div>
        <div class="card-body">
            <div class="grid-2">
                @unless($merchant->exists)
                    <div class="field"><label>{{ __('Client') }}</label><select name="client" required>@foreach($clients as $c)<option value="{{ $c->public_id }}" @selected(old('client', $selectedClient) === $c->public_id)>{{ $c->name }}</option>@endforeach</select></div>
                @else
                    <div class="field"><label>{{ __('Client') }}</label><input value="{{ $merchant->client->name }}" disabled></div>
                @endunless
                <div class="field"><label>{{ __('Name') }}</label><input name="name" value="{{ old('name', $merchant->name) }}" required></div>
                <div class="field"><label>{{ __('Provider') }}</label><select name="provider" required>@foreach($providers as $p)<option value="{{ $p->code }}" @selected(old('provider', $merchant->provider?->code) === $p->code)>{{ $p->name }} ({{ __($p->status->value) }})</option>@endforeach</select></div>
            </div>
            <label class="check"><input type="checkbox" name="is_default" value="1" @checked(old('is_default', $merchant->is_default))> {{ __('Default merchant for this client') }}</label>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><h2>{{ __('Credentials') }}</h2></div>
        <div class="card-body">
            <p class="muted small" style="margin-top:0">{{ __('Stored encrypted. Existing secret values are never displayed; leave a field empty to keep its current value.') }}</p>
            <details style="margin-bottom:14px"><summary>{{ __('Required credentials per provider') }}</summary>
                <div class="table-wrap" style="margin-top:8px"><table>
                    @foreach($requirements as $code => $fields)
                        <tr><td class="mono ltr">{{ $code }}</td><td>@forelse($fields as $key => $label)<div><code>{{ $key }}</code> — {{ __($label) }}</div>@empty <span class="muted">{{ __('none') }}</span> @endforelse</td></tr>
                    @endforeach
                </table></div>
            </details>
            @php $configured = $merchant->exists ? array_keys($merchant->credentials()) : []; @endphp
            <div class="grid-2">
            @foreach(['merchant_identifier' => 'Merchant identifier', 'terminal_identifier' => 'Terminal identifier', 'username' => 'Username', 'password' => 'Password', 'api_key' => 'API key'] as $key => $label)
                <div class="field">
                    <label>{{ __($label) }} @if(in_array($key, $configured))<span class="badge b-success">{{ __('configured') }}</span>@endif</label>
                    <input type="{{ in_array($key, ['password', 'api_key']) ? 'password' : 'text' }}" name="credentials[{{ $key }}]" value="" autocomplete="new-password" dir="ltr" placeholder="{{ $key }}">
                </div>
            @endforeach
            </div>
            <div class="field"><label>{{ __('Extra provider config (JSON object, encrypted)') }}</label><textarea name="extra_config" placeholder='{"key": "value"}'></textarea></div>
        </div>
    </div>
    <button class="btn" type="submit">@include('admin._icon', ['name' => 'check']) {{ __('Save') }}</button>
</form>
@endsection
