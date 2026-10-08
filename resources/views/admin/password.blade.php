@extends('admin.layout')
@section('title', __('Change password'))
@section('content')
<div class="card" style="max-width:520px">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.password.update') }}">
            @csrf @method('PUT')
            <input type="text" name="username" value="{{ auth()->user()->email }}" autocomplete="username" hidden>
            <div class="field"><label for="current_password">{{ __('Current password') }}</label><input id="current_password" type="password" name="current_password" required autocomplete="current-password" dir="ltr"></div>
            <div class="field"><label for="password">{{ __('New password') }}</label><input id="password" type="password" name="password" required minlength="12" autocomplete="new-password" dir="ltr"><span class="hint">{{ __('At least 12 characters.') }}</span></div>
            <div class="field"><label for="password_confirmation">{{ __('Repeat new password') }}</label><input id="password_confirmation" type="password" name="password_confirmation" required minlength="12" autocomplete="new-password" dir="ltr"></div>
            <button class="btn" type="submit">@include('admin._icon', ['name' => 'key']) {{ __('Change password') }}</button>
        </form>
    </div>
</div>
@endsection
