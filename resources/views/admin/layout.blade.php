<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'Admin') - Tech-Kala Payments</title>
    <style>
        :root { --bg:#f6f7f9; --card:#fff; --text:#1d2330; --muted:#6b7385; --border:#e1e4ea; --accent:#1f6feb; --ok:#1a7f37; --bad:#cf222e; --warn:#9a6700; }
        @media (prefers-color-scheme: dark) { :root { --bg:#0f1218; --card:#171b23; --text:#e6e9ef; --muted:#9aa3b5; --border:#2a303c; } }
        * { box-sizing:border-box; }
        body { margin:0; font:14px/1.5 system-ui, sans-serif; background:var(--bg); color:var(--text); }
        header { background:var(--card); border-bottom:1px solid var(--border); padding:10px 16px; display:flex; gap:16px; align-items:center; flex-wrap:wrap; }
        header a { color:var(--text); text-decoration:none; } header strong { margin-right:12px; }
        main { padding:16px; max-width:1300px; margin:0 auto; }
        .card { background:var(--card); border:1px solid var(--border); border-radius:8px; padding:16px; margin-bottom:16px; overflow-x:auto; }
        table { width:100%; border-collapse:collapse; } th, td { text-align:left; padding:6px 8px; border-bottom:1px solid var(--border); vertical-align:top; }
        th { color:var(--muted); font-weight:600; font-size:12px; text-transform:uppercase; }
        a { color:var(--accent); }
        input, select, textarea { font:inherit; padding:6px 8px; border:1px solid var(--border); border-radius:6px; background:var(--bg); color:var(--text); max-width:100%; }
        textarea { width:100%; min-height:90px; font-family:monospace; }
        label { display:block; margin:10px 0 4px; color:var(--muted); }
        button, .btn { font:inherit; padding:6px 12px; border-radius:6px; border:1px solid var(--accent); background:var(--accent); color:#fff; cursor:pointer; text-decoration:none; display:inline-block; }
        button.secondary, .btn.secondary { background:transparent; color:var(--accent); }
        button.danger { background:var(--bad); border-color:var(--bad); }
        form.inline { display:inline; }
        .flash { padding:10px 14px; border-radius:6px; margin-bottom:16px; border:1px solid var(--border); background:var(--card); }
        .flash.ok { border-color:var(--ok); } .flash.err { border-color:var(--bad); }
        .secret { font-family:monospace; background:var(--bg); padding:2px 6px; border-radius:4px; word-break:break-all; }
        .badge { display:inline-block; padding:1px 8px; border-radius:10px; font-size:12px; border:1px solid var(--border); }
        .s-paid, .s-delivered, .s-active { color:var(--ok); border-color:var(--ok); }
        .s-failed, .s-disabled, .s-revoked, .s-expired, .s-cancelled { color:var(--bad); border-color:var(--bad); }
        .s-verifying, .s-callback_received, .s-pending, .s-processing { color:var(--warn); border-color:var(--warn); }
        .grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px; }
        .stat { font-size:24px; font-weight:700; } .muted { color:var(--muted); }
        pre { white-space:pre-wrap; word-break:break-all; font-size:12px; background:var(--bg); padding:8px; border-radius:6px; margin:0; }
        .filters { display:flex; gap:8px; flex-wrap:wrap; align-items:end; }
    </style>
</head>
<body>
@auth
<header>
    <strong>Tech-Kala Payments</strong>
    <a href="{{ route('admin.dashboard') }}">Dashboard</a>
    <a href="{{ route('admin.clients.index') }}">Clients</a>
    <a href="{{ route('admin.merchants.index') }}">Merchants</a>
    <a href="{{ route('admin.payments.index') }}">Payments</a>
    <a href="{{ route('admin.webhooks.index') }}">Webhooks</a>
    <a href="{{ route('admin.providers.index') }}">Providers</a>
    <a href="{{ route('admin.audit-logs.index') }}">Audit log</a>
    <form class="inline" method="POST" action="{{ route('admin.logout') }}" style="margin-left:auto">@csrf<button class="secondary">Logout</button></form>
</header>
@endauth
<main>
    @if(session('status'))<div class="flash ok">{{ session('status') }}</div>@endif
    @if(session('error'))<div class="flash err">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="flash err">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
    @if(session('secrets'))
        <div class="flash ok">
            <strong>Copy these now. They will not be shown again.</strong>
            @foreach(session('secrets') as $label => $value)
                <div>{{ $label }}: <span class="secret">{{ $value }}</span></div>
            @endforeach
        </div>
    @endif
    @yield('content')
</main>
</body>
</html>
