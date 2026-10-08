@extends('admin.layout')
@section('title', $client->exists ? __('Edit client') : __('New client'))
@section('content')
<div class="card" style="max-width:640px">
    <h2>{{ $client->exists ? __('Edit').' '.$client->name : __('New client') }}</h2>
    <form method="POST" action="{{ $client->exists ? route('admin.clients.update', $client) : route('admin.clients.store') }}">
        @csrf
        @if($client->exists) @method('PUT') @endif
        <label>{{ __('Name') }}</label><input name="name" value="{{ old('name', $client->name) }}" required style="width:100%">
        <label>{{ __('Slug') }} <span class="muted">({{ __('lowercase English letters, digits and -') }})</span></label><input name="slug" value="{{ old('slug', $client->slug) }}" required pattern="[a-z0-9-]+" style="width:100%" dir="ltr">
        <label>{{ __('Webhook URL (HTTPS)') }}</label><input name="webhook_url" value="{{ old('webhook_url', $client->webhook_url) }}" style="width:100%" dir="ltr">
        <label>{{ __('Default return URL (HTTPS)') }}</label><input name="return_url" value="{{ old('return_url', $client->return_url) }}" style="width:100%" dir="ltr">
        <p><button type="submit">{{ __('Save') }}</button></p>
    </form>
    @unless($client->exists)<p class="muted">{{ __('An API credential and webhook secret are generated automatically and shown once.') }}</p>@endunless
</div>
@endsection
