@extends('admin.layout')
@section('title', __('Login'))
@section('content')
<div class="card">
    <div class="card-body" style="padding:28px">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:18px">
            <div class="brand-mark" style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#3b82f6,#7c3aed);display:grid;place-items:center;color:#fff">@include('admin._icon', ['name' => 'payments', 'size' => 20])</div>
            <div><div style="font-weight:700;font-size:16px">{{ config('app.name') }}</div><div class="muted small">{{ __('Admin login') }}</div></div>
        </div>
        <form method="POST" action="{{ route('admin.login') }}">
            @csrf
            <div class="field"><label for="email">{{ __('Email') }}</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus dir="ltr" autocomplete="username"></div>
            <div class="field"><label for="password">{{ __('Password') }}</label><input id="password" type="password" name="password" required dir="ltr" autocomplete="current-password"></div>
            <button class="btn" type="submit" style="width:100%;justify-content:center;padding:10px">{{ __('Login') }}</button>
        </form>
    </div>
</div>
@endsection
