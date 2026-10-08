@extends('admin.layout')
@section('title', $client->exists ? __('Edit client') : __('New client'))
@section('content')
<form method="POST" action="{{ $client->exists ? route('admin.clients.update', $client) : route('admin.clients.store') }}" style="max-width:860px">
    @csrf
    @if($client->exists) @method('PUT') @endif
    <div class="card">
        <div class="card-head"><h2>{{ __('Client details') }}</h2></div>
        <div class="card-body">
            <div class="grid-2">
                <div class="field"><label>{{ __('Name') }}</label><input name="name" value="{{ old('name', $client->name) }}" required></div>
                <div class="field"><label>{{ __('Slug') }}</label><input name="slug" value="{{ old('slug', $client->slug) }}" required pattern="[a-z0-9-]+" dir="ltr"><span class="hint">{{ __('lowercase English letters, digits and -') }}</span></div>
            </div>
            <div class="field"><label>{{ __('Webhook URL (HTTPS)') }}</label><input name="webhook_url" value="{{ old('webhook_url', $client->webhook_url) }}" dir="ltr" placeholder="https://"></div>
            <div class="field"><label>{{ __('Default return URL (HTTPS)') }}</label><input name="return_url" value="{{ old('return_url', $client->return_url) }}" dir="ltr" placeholder="https://"></div>
            @unless($client->exists)<p class="muted small">{{ __('An API credential and webhook secret are generated automatically and shown once.') }}</p>@endunless
        </div>
    </div>
    <div class="card">
        <div class="card-head"><h2>{{ __('Settlement') }}</h2><span class="muted small">{{ __('Optional. For clients paid through our own terminal.') }}</span></div>
        <div class="card-body">
            <div class="grid-2">
                <div class="field"><label>{{ __('Commission (%)') }}</label><input name="commission_percent" type="number" step="0.01" min="0" max="100" value="{{ old('commission_percent', $client->exists ? $client->commission_bps / 100 : 0) }}" dir="ltr"><span class="hint">{{ __('0 = no percentage commission. Example: 1.5') }}</span></div>
                <div class="field"><label>{{ __('Fixed commission per payment (Rial)') }}</label><input name="commission_fixed_irr" type="number" min="0" value="{{ old('commission_fixed_irr', $client->commission_fixed_irr ?? 0) }}" dir="ltr"></div>
                <div class="field"><label>{{ __('Hold period (hours)') }}</label><input name="settlement_delay_hours" type="number" min="0" max="720" value="{{ old('settlement_delay_hours', $client->settlement_delay_hours ?? 0) }}" dir="ltr"><span class="hint">{{ __('Money becomes payable this many hours after each payment. 0 = immediately.') }}</span></div>
                <div class="field"><label>{{ __('IBAN (Sheba)') }}</label><input name="iban" value="{{ old('iban', $client->iban) }}" dir="ltr" placeholder="IR..."></div>
                <div class="field"><label>{{ __('Account holder') }}</label><input name="account_holder" value="{{ old('account_holder', $client->account_holder) }}"></div>
            </div>
        </div>
    </div>
    <button class="btn" type="submit">@include('admin._icon', ['name' => 'check']) {{ __('Save') }}</button>
</form>
@endsection
