@extends('admin.layout')
@section('title', __('Login'))
@section('content')
<div class="card" style="max-width:380px;margin:60px auto">
    <h2>{{ __('Admin login') }}</h2>
    <form method="POST" action="{{ route('admin.login') }}">
        @csrf
        <label>{{ __('Email') }}</label><input type="email" name="email" value="{{ old('email') }}" required autofocus style="width:100%" dir="ltr">
        <label>{{ __('Password') }}</label><input type="password" name="password" required style="width:100%" autocomplete="current-password" dir="ltr">
        <p><button type="submit">{{ __('Login') }}</button></p>
    </form>
</div>
@endsection
