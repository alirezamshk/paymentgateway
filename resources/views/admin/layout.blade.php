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
    ];
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title', __('Admin')) · {{ config('app.name') }}</title>
    <style>
        @font-face { font-family: "Vazirmatn"; src: url("{{ asset('fonts/Vazirmatn-wght.woff2') }}") format("woff2"); font-weight: 100 900; font-display: swap; }
        :root {
            --bg:#f3f5f9; --surface:#ffffff; --surface-2:#f7f9fc; --text:#0f172a; --text-2:#334155; --muted:#64748b; --border:#e3e8ef;
            --primary:#2563eb; --primary-hover:#1d4ed8; --primary-soft:#eaf1ff; --primary-text:#1d4ed8;
            --success:#15803d; --success-soft:#e8f6ee; --danger:#dc2626; --danger-soft:#fdecec; --warning:#b45309; --warning-soft:#fdf3e2;
            --info:#0369a1; --info-soft:#e6f3fa;
            --sidebar:#0b1222; --sidebar-2:#141d33; --sidebar-text:#cbd5e1; --sidebar-muted:#7c8aa5;
            --series-0:#9a9893; --series-1:#2a78d6; --series-2:#eb6834; --series-3:#1baf7a; --series-4:#eda100; --series-5:#e87ba4; --series-6:#008300; --series-7:#4a3aa7;
            --grid:#e8ecf2;
            --radius:12px; --shadow:0 1px 2px rgba(15,23,42,.05), 0 1px 1px rgba(15,23,42,.03);
            color-scheme: light;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg:#0b1020; --surface:#121a2c; --surface-2:#0f1626; --text:#e6ebf5; --text-2:#c4cede; --muted:#8b98b1; --border:#22304a;
                --primary:#4f8cff; --primary-hover:#6b9dff; --primary-soft:#16264a; --primary-text:#8db4ff;
                --success:#4ade80; --success-soft:#11291c; --danger:#f87171; --danger-soft:#2c1517; --warning:#fbbf24; --warning-soft:#2b2210;
                --info:#38bdf8; --info-soft:#0f2433;
                --sidebar:#070b16; --sidebar-2:#111a2e;
                --series-0:#6f6e69; --series-1:#3987e5; --series-2:#d95926; --series-3:#199e70; --series-4:#c98500; --series-5:#d55181; --series-6:#008300; --series-7:#9085e9;
                --grid:#1d2840;
                --shadow:none; color-scheme: dark;
            }
        }
        * { box-sizing:border-box; }
        html, body { margin:0; }
        body { font:14px/1.65 "Vazirmatn", system-ui, -apple-system, "Segoe UI", sans-serif; background:var(--bg); color:var(--text); -webkit-font-smoothing:antialiased; }
        a { color:var(--primary-text); text-decoration:none; } a:hover { text-decoration:underline; }
        .icon { flex:none; vertical-align:middle; }
        code, pre, .mono { font-family: ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace; font-size:12.5px; }
        .ltr { direction:ltr; unicode-bidi:isolate; text-align:start; }
        [hidden] { display:none !important; }

        /* Shell */
        .shell { display:grid; grid-template-columns: 252px minmax(0,1fr); min-height:100vh; }
        .sidebar { background:var(--sidebar); color:var(--sidebar-text); }
        .sidebar-inner { padding:20px 14px; position:sticky; top:0; height:100vh; display:flex; flex-direction:column; gap:6px; }
        .brand { display:flex; align-items:center; gap:10px; padding:4px 10px 18px; color:#fff; font-weight:700; font-size:15px; }
        .brand-mark { width:32px; height:32px; border-radius:9px; background:linear-gradient(135deg,#3b82f6,#7c3aed); display:grid; place-items:center; color:#fff; }
        .brand small { display:block; font-weight:400; font-size:11.5px; color:var(--sidebar-muted); }
        .nav a { display:flex; align-items:center; gap:11px; padding:9px 12px; border-radius:9px; color:var(--sidebar-text); font-weight:500; }
        .nav a:hover { background:var(--sidebar-2); text-decoration:none; color:#fff; }
        .nav a.active { background:#2563eb; color:#fff; }
        .sidebar-foot { margin-top:auto; border-top:1px solid #1e293b; padding-top:12px; font-size:12.5px; color:var(--sidebar-muted); }
        .sidebar-foot .who { padding:0 12px 8px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .main { min-width:0; display:flex; flex-direction:column; }
        .topbar { display:flex; align-items:center; gap:12px; padding:16px 28px; background:var(--surface); border-bottom:1px solid var(--border); position:sticky; top:0; z-index:5; }
        .topbar h1 { font-size:18px; margin:0; font-weight:700; }
        .topbar .spacer { flex:1; }
        .content { padding:24px 28px 48px; max-width:1360px; width:100%; }

        /* Language switch */
        .lang { display:inline-flex; border:1px solid var(--border); border-radius:999px; padding:3px; background:var(--surface-2); }
        .lang form { margin:0; }
        .lang button { border:0; background:transparent; color:var(--muted); padding:4px 12px; border-radius:999px; font:inherit; font-size:12.5px; font-weight:600; cursor:pointer; }
        .lang button.on { background:var(--surface); color:var(--text); box-shadow:var(--shadow); }

        /* Components */
        .card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); box-shadow:var(--shadow); margin-bottom:20px; }
        .card-head { display:flex; align-items:center; gap:10px; padding:16px 20px; border-bottom:1px solid var(--border); }
        .card-head h2, .card-head h3 { margin:0; font-size:15px; font-weight:700; }
        .card-head .actions { margin-inline-start:auto; display:flex; gap:8px; flex-wrap:wrap; }
        .card-body { padding:18px 20px; }
        .card-body.flush { padding:0; }
        .stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:16px; margin-bottom:20px; }
        .stat { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:16px 18px; box-shadow:var(--shadow); display:flex; gap:14px; align-items:flex-start; }
        .stat .ic { width:40px; height:40px; border-radius:10px; display:grid; place-items:center; background:var(--primary-soft); color:var(--primary-text); }
        .stat .ic.success { background:var(--success-soft); color:var(--success); } .stat .ic.danger { background:var(--danger-soft); color:var(--danger); } .stat .ic.warning { background:var(--warning-soft); color:var(--warning); }
        .stat .label { color:var(--muted); font-size:12.5px; }
        .stat .value { font-size:21px; font-weight:700; line-height:1.3; white-space:nowrap; }
        .stat > div:last-child { min-width:0; }
        .stat .sub { color:var(--muted); font-size:12px; }

        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; }
        th, td { text-align:start; padding:11px 16px; border-bottom:1px solid var(--border); vertical-align:middle; }
        th { color:var(--muted); font-weight:600; font-size:12px; background:var(--surface-2); white-space:nowrap; }
        tbody tr:hover td { background:var(--surface-2); }
        tr:last-child td { border-bottom:0; }
        td.num, th.num { text-align:end; font-variant-numeric:tabular-nums; white-space:nowrap; }
        .pos { color:var(--success); } .neg { color:var(--danger); }
        .kv { display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:0 32px; }
        .kv > div { display:flex; justify-content:space-between; gap:16px; padding:10px 0; border-bottom:1px dashed var(--border); }
        .kv dt { color:var(--muted); } .kv dd { margin:0; font-weight:500; text-align:end; word-break:break-word; }

        .badge { display:inline-flex; align-items:center; gap:5px; padding:2px 10px; border-radius:999px; font-size:12px; font-weight:600; background:var(--surface-2); color:var(--text-2); border:1px solid var(--border); white-space:nowrap; }
        .badge::before { content:""; width:6px; height:6px; border-radius:50%; background:currentColor; opacity:.8; }
        .b-success { background:var(--success-soft); color:var(--success); border-color:transparent; }
        .b-danger { background:var(--danger-soft); color:var(--danger); border-color:transparent; }
        .b-warning { background:var(--warning-soft); color:var(--warning); border-color:transparent; }
        .b-info { background:var(--info-soft); color:var(--info); border-color:transparent; }
        .b-muted { color:var(--muted); }

        .btn { display:inline-flex; align-items:center; gap:7px; padding:8px 14px; border-radius:9px; border:1px solid transparent; background:var(--primary); color:#fff; font:inherit; font-weight:600; font-size:13px; cursor:pointer; text-decoration:none; white-space:nowrap; }
        .btn:hover { background:var(--primary-hover); text-decoration:none; }
        .btn.secondary { background:var(--surface); color:var(--text-2); border-color:var(--border); } .btn.secondary:hover { background:var(--surface-2); }
        .btn.danger { background:var(--danger); } .btn.ghost { background:transparent; color:var(--primary-text); padding:6px 8px; }
        .btn.sm { padding:5px 10px; font-size:12.5px; }
        form.inline { display:inline; }

        .field { display:flex; flex-direction:column; gap:6px; margin-bottom:14px; }
        .field label { font-weight:600; font-size:13px; color:var(--text-2); }
        .field .hint { color:var(--muted); font-size:12px; }
        .grid-2 { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:0 18px; }
        input, select, textarea { font:inherit; padding:9px 12px; border:1px solid var(--border); border-radius:9px; background:var(--surface); color:var(--text); width:100%; }
        input:focus, select:focus, textarea:focus { outline:2px solid var(--primary-soft); border-color:var(--primary); }
        input[type=checkbox] { width:auto; }
        textarea { min-height:110px; font-family:ui-monospace, Menlo, Consolas, monospace; direction:ltr; text-align:left; }
        .check { display:flex; align-items:center; gap:8px; font-weight:500; }
        .filters { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:12px; align-items:end; }
        .filters .field { margin:0; }

        .alert { display:flex; gap:10px; align-items:flex-start; padding:12px 16px; border-radius:10px; margin-bottom:18px; border:1px solid; }
        .alert.ok { background:var(--success-soft); color:var(--success); border-color:transparent; }
        .alert.err { background:var(--danger-soft); color:var(--danger); border-color:transparent; }
        .alert.secret { background:var(--warning-soft); color:var(--text); border-color:transparent; flex-direction:column; }
        .secret-value { font-family:ui-monospace, Menlo, Consolas, monospace; background:var(--surface); border:1px solid var(--border); padding:4px 8px; border-radius:6px; word-break:break-all; direction:ltr; unicode-bidi:isolate; }
        .muted { color:var(--muted); } .small { font-size:12px; }
        .empty { padding:36px; text-align:center; color:var(--muted); }
        pre { white-space:pre-wrap; word-break:break-all; background:var(--surface-2); border:1px solid var(--border); padding:10px; border-radius:8px; margin:0; direction:ltr; text-align:left; }
        details summary { cursor:pointer; color:var(--primary-text); }
        .pager { display:flex; gap:6px; align-items:center; justify-content:space-between; padding:12px 16px; border-top:1px solid var(--border); color:var(--muted); font-size:12.5px; }
        .pager .pages { display:flex; gap:6px; }

        .timeline { list-style:none; margin:0; padding:0; }
        .timeline li { display:grid; grid-template-columns:150px 1fr; gap:14px; padding:10px 20px; border-bottom:1px solid var(--border); }
        .timeline li:last-child { border-bottom:0; }
        .timeline .ev { font-weight:600; }

        /* Charts */
        .chart { width:100%; height:auto; display:block; direction:ltr; }
        .chart text { fill:var(--muted); font-size:12px; font-family:inherit; }
        .chart .grid { stroke:var(--grid); stroke-width:1; }
        .chart .axis { stroke:var(--border); stroke-width:1; }
        .chart .hit { fill:transparent; } .chart .hit:hover { fill:var(--surface-2); }
        .legend { display:flex; flex-wrap:wrap; gap:8px 18px; padding:12px 20px 0; }
        .legend .item { display:flex; align-items:center; gap:8px; font-size:13px; color:var(--text-2); }
        .legend .sw { width:12px; height:12px; border-radius:3px; flex:none; }
        .legend .val { color:var(--muted); font-size:12px; }
        .seg-tabs { display:inline-flex; border:1px solid var(--border); border-radius:10px; padding:3px; background:var(--surface-2); }
        .seg-tabs a { padding:5px 14px; border-radius:8px; color:var(--muted); font-weight:600; font-size:13px; }
        .seg-tabs a:hover { text-decoration:none; color:var(--text); }
        .seg-tabs a.on { background:var(--surface); color:var(--text); box-shadow:var(--shadow); }

        /* Auth page */
        .auth { min-height:100vh; display:grid; place-items:center; padding:24px; background:radial-gradient(1200px 600px at 10% -10%, var(--primary-soft), transparent), var(--bg); }
        .auth .card { width:100%; max-width:400px; }

        @media (max-width: 960px) {
            .shell { grid-template-columns:minmax(0,1fr); }
            .topbar { flex-wrap:wrap; }
            .sidebar-inner { position:static; height:auto; flex-direction:row; align-items:center; overflow-x:auto; padding:10px 12px; }
            .brand { padding:0 8px 0 0; } .brand small { display:none; }
            .nav { display:flex; gap:2px; } .nav a span { display:none; }
            .sidebar-foot { display:none; }
            .topbar, .content { padding-inline:16px; }
            .timeline li { grid-template-columns:1fr; gap:2px; }
        }
    </style>
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
