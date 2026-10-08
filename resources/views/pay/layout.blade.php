<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="origin">
    <title>@yield('title', 'پرداخت') - {{ config('app.name') }}</title>
    <style>
        :root { --bg:#f5f6f8; --card:#fff; --text:#1d2330; --muted:#6b7385; --accent:#1f6feb; --ok:#1a7f37; --bad:#cf222e; --border:#e3e6ec; }
        @media (prefers-color-scheme: dark) { :root { --bg:#0f1218; --card:#171b23; --text:#e6e9ef; --muted:#9aa3b5; --border:#2a303c; } }
        * { box-sizing: border-box; }
        body { margin:0; font-family: Tahoma, "Vazirmatn", system-ui, sans-serif; background:var(--bg); color:var(--text); display:flex; min-height:100vh; align-items:center; justify-content:center; padding:16px; }
        .card { background:var(--card); border:1px solid var(--border); border-radius:12px; padding:24px; width:100%; max-width:420px; }
        h1 { font-size:18px; margin:0 0 16px; }
        dl { display:grid; grid-template-columns:auto 1fr; gap:10px 16px; margin:0 0 20px; }
        dt { color:var(--muted); } dd { margin:0; font-weight:bold; word-break:break-word; }
        .btn { display:block; width:100%; padding:12px; border:0; border-radius:8px; background:var(--accent); color:#fff; font-size:15px; cursor:pointer; font-family:inherit; }
        .btn.secondary { background:var(--muted); margin-top:8px; }
        .status-paid { color:var(--ok); } .status-failed, .status-expired, .status-cancelled { color:var(--bad); }
        .muted { color:var(--muted); font-size:13px; }
        .redirecting { text-align:center; }
        .spinner { width:36px; height:36px; margin:8px auto 16px; border:3px solid var(--border); border-top-color:var(--accent); border-radius:50%; animation:spin .8s linear infinite; }
        @keyframes spin { to { transform:rotate(360deg); } }
        @media (prefers-reduced-motion: reduce) { .spinner { animation:none; } }
    </style>
</head>
<body>
<main class="card">@yield('content')</main>
@yield('scripts')
</body>
</html>
