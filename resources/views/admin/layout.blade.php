@php
    $rtl = app()->getLocale() === 'fa';
    $nav = [
        ['admin.dashboard', 'admin.dashboard', 'dashboard', 'Dashboard'],
        ['admin.payments.index', 'admin.payments.*', 'payments', 'Payments'],
        ['admin.reports.sales', 'admin.reports.*', 'chart', 'Sales report'],
        ['admin.settlements.index', 'admin.settlements.*', 'settlements', 'Settlements'],
        ['admin.clients.index', 'admin.clients.*', 'sites', 'Clients'],
        ['admin.merchants.index', 'admin.merchants.*', 'merchants', 'Merchants'],
        ['admin.webhooks.index', 'admin.webhooks.*', 'webhooks', 'Webhooks'],
        ['admin.providers.index', 'admin.providers.*', 'providers', 'Providers'],
        ['admin.audit-logs.index', 'admin.audit-logs.*', 'audit', 'Audit log'],
        ['docs', 'docs', 'code', 'API docs'],
    ];
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title', __('Admin')) · {{ config('app.name') }}</title>
    @include('admin._styles')
</head>
<body>
@auth
<div class="shell">
    <aside class="sidebar"><div class="sidebar-inner">
        <div class="brand">
            <div class="brand-mark">@include('admin._icon', ['name' => 'payments', 'size' => 18])</div>
            <div>{{ config('app.name') }}<small>{{ __('Payment gateway admin') }}</small></div>
        </div>
        <nav class="nav">
            @foreach($nav as [$route, $pattern, $icon, $label])
                <a href="{{ route($route) }}" class="{{ request()->routeIs($pattern) ? 'active' : '' }}" title="{{ __($label) }}">
                    @include('admin._icon', ['name' => $icon])<span>{{ __($label) }}</span>
                </a>
            @endforeach
        </nav>
        <div class="sidebar-foot">
            <div class="who">{{ auth()->user()->email }}</div>
            <a class="btn ghost" style="color:var(--sidebar-text)" href="{{ route('admin.password.edit') }}">@include('admin._icon', ['name' => 'key']) {{ __('Change password') }}</a>
            <form method="POST" action="{{ route('admin.logout') }}">@csrf
                <button class="btn ghost" style="color:var(--sidebar-text)">@include('admin._icon', ['name' => 'logout']) {{ __('Logout') }}</button>
            </form>
        </div>
    </div></aside>
    <div class="main">
        <header class="topbar">
            <h1>@yield('title', __('Admin'))</h1>
            <div class="spacer"></div>
            @yield('actions')
            @include('admin._lang')
        </header>
        <main class="content">
            @include('admin._flash')
            @yield('content')
        </main>
    </div>
</div>
@else
<div class="auth">
    <div style="width:100%;max-width:400px">
        <div style="display:flex;justify-content:flex-end;margin-bottom:12px">@include('admin._lang')</div>
        @include('admin._flash')
        @yield('content')
    </div>
</div>
@endauth
</body>
</html>
