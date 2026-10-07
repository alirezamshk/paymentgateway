@extends('admin.layout')
@section('title', 'Login')
@section('content')
<div class="card" style="max-width:380px;margin:60px auto">
    <h2>Admin login</h2>
    <form method="POST" action="{{ route('admin.login') }}">
        @csrf
        <label>Email</label><input type="email" name="email" value="{{ old('email') }}" required autofocus style="width:100%">
        <label>Password</label><input type="password" name="password" required style="width:100%" autocomplete="current-password">
        <p><button type="submit">Login</button></p>
    </form>
</div>
@endsection
