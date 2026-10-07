@extends('admin.layout')
@section('title', 'Configure '.$provider->name)
@section('content')
<div class="card" style="max-width:640px">
    <h2>{{ $provider->name }} ({{ $provider->code }})</h2>
    <form method="POST" action="{{ route('admin.providers.update', $provider) }}">
        @csrf @method('PUT')
        <label>Name</label><input name="name" value="{{ old('name', $provider->name) }}" required style="width:100%">
        <label>Config (JSON, non-secret settings only, e.g. {"sandbox": true} or endpoint overrides)</label>
        <textarea name="config">{{ old('config', json_encode($provider->config ?: new stdClass, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) }}</textarea>
        <p><button type="submit">Save</button></p>
    </form>
</div>
@endsection
