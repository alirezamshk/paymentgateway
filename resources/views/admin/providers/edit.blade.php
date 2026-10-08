@extends('admin.layout')
@section('title', __('Configure').' — '.$provider->name)
@section('content')
<form method="POST" action="{{ route('admin.providers.update', $provider) }}" style="max-width:760px">
    @csrf @method('PUT')
    <div class="card">
        <div class="card-head"><h2>{{ $provider->name }}</h2><span class="badge mono ltr">{{ $provider->code }}</span></div>
        <div class="card-body">
            <div class="field"><label>{{ __('Name') }}</label><input name="name" value="{{ old('name', $provider->name) }}" required></div>
            <div class="field"><label>{{ __('Config') }}</label><textarea name="config">{{ old('config', json_encode($provider->config ?: new stdClass, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) }}</textarea><span class="hint">{{ __('Config (JSON, non-secret settings only, e.g. {"sandbox": true} or endpoint overrides)') }}</span></div>
        </div>
    </div>
    <button class="btn" type="submit">@include('admin._icon', ['name' => 'check']) {{ __('Save') }}</button>
</form>
@endsection
