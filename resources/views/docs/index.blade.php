@php
    $fa = app()->getLocale() === 'fa';
    /** Static, trusted copy only — never user input. */
    $t = fn (string $faText, string $enText) => $fa ? $faText : $enText;
    $toc = [
        'overview' => $t('شروع', 'Getting started'),
        'auth' => $t('امضای درخواست‌ها', 'Signing requests'),
        'create' => $t('ساخت پرداخت', 'Create a payment'),
        'gateways' => $t('انتخاب درگاه', 'Choosing a gateway'),
        'return' => $t('بازگشت مشتری', 'Customer return'),
        'webhooks' => $t('وب‌هوک', 'Webhooks'),
        'endpoints' => $t('سایر endpointها', 'Other endpoints'),
        'errors' => $t('خطاها', 'Errors'),
        'samples' => $t('نمونه‌کد', 'Code samples'),
        'checklist' => $t('چک‌لیست', 'Checklist'),
    ];
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $fa ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $t('مستندات API', 'API documentation') }} · {{ config('app.name') }}</title>
    @include('admin._styles')
    <style>
        .docs-top { position:sticky; top:0; z-index:5; display:flex; align-items:center; gap:12px; padding:12px 24px; background:var(--surface); border-bottom:1px solid var(--border); }
        .docs-top .brand-mark { width:34px; height:34px; border-radius:9px; background:linear-gradient(135deg,#3b82f6,#7c3aed); display:grid; place-items:center; color:#fff; }
        .docs-top .name { font-weight:700; }
        .docs-top .spacer { flex:1; }
        .docs { display:grid; grid-template-columns:220px minmax(0,1fr); gap:28px; max-width:1180px; margin:0 auto; padding:24px; }
        .toc { position:sticky; top:76px; align-self:start; display:flex; flex-direction:column; gap:2px; }
        .toc a { color:var(--text-2); text-decoration:none; padding:6px 10px; border-radius:8px; font-size:13.5px; }
        .toc a:hover { background:var(--primary-soft); color:var(--primary-text); }
        .toc .dl { margin-top:12px; }
        .doc section { scroll-margin-top:80px; }
        .doc h2 { font-size:19px; margin:0 0 12px; }
        .doc h3 { font-size:15px; margin:20px 0 8px; }
        .doc p, .doc li { color:var(--text-2); }
        .doc ul, .doc ol { padding-inline-start:20px; }
        .doc .card-body > :first-child { margin-top:0; }
        .doc code { background:var(--surface-2); border:1px solid var(--border); border-radius:5px; padding:1px 5px; direction:ltr; unicode-bidi:isolate; }
        .doc pre code { background:none; border:0; padding:0; }
        .doc pre { white-space:pre; overflow:auto; word-break:normal; padding:14px; line-height:1.55; }
        .doc table code { white-space:nowrap; }
        .doc details { border:1px solid var(--border); border-radius:10px; margin-bottom:10px; background:var(--surface); }
        .doc details summary { cursor:pointer; padding:10px 14px; font-weight:600; }
        .doc details[open] summary { border-bottom:1px solid var(--border); }
        .doc details pre { border:0; border-radius:0 0 10px 10px; }
        .flow { direction:ltr; text-align:left; }
        .kv { display:grid; grid-template-columns:max-content minmax(0,1fr); gap:6px 16px; }
        .kv dt { color:var(--muted); }
        .kv dd { margin:0; word-break:break-all; }
        .note { border-inline-start:3px solid var(--primary); background:var(--primary-soft); padding:10px 14px; border-radius:8px; color:var(--text-2); margin:12px 0; }
        .note.warn { border-color:var(--warning); background:var(--warning-soft); }
        @media (max-width: 900px) {
            .docs { grid-template-columns:minmax(0,1fr); padding:16px; }
            .toc { position:static; flex-direction:row; flex-wrap:wrap; }
            .docs-top { padding:10px 16px; }
        }
    </style>
</head>
<body>
<header class="docs-top">
    <div class="brand-mark">@include('admin._icon', ['name' => 'code', 'size' => 18])</div>
    <div><div class="name">{{ config('app.name') }}</div><div class="muted small">{{ $t('مستندات API برای برنامه‌نویسان', 'API documentation for developers') }}</div></div>
    <div class="spacer"></div>
    @auth<a class="btn secondary sm" href="{{ route('admin.dashboard') }}">{{ $t('پنل مدیریت', 'Admin panel') }}</a>@endauth
    @include('admin._lang')
</header>

<div class="docs">
    <nav class="toc" aria-label="{{ $t('فهرست', 'Contents') }}">
        @foreach($toc as $id => $label)<a href="#{{ $id }}">{{ $label }}</a>@endforeach
        <a class="btn secondary sm dl" href="{{ route('docs.openapi') }}">@include('admin._icon', ['name' => 'download']) OpenAPI (YAML)</a>
    </nav>

    <main class="doc">
        {{-- Overview --}}
        <section id="overview" class="card"><div class="card-body">
            <h2>{{ $toc['overview'] }}</h2>
            <p>{{ $t(
                'با این API سایت یا پنل شما پرداخت آنلاین می‌گیرد، بدون اینکه مستقیم با بانک کار کند. شما پرداخت را می‌سازید، مشتری را به آدرس پرداخت می‌فرستید و نتیجه را با وب‌هوک امضاشده دریافت می‌کنید.',
                'This API lets your site or panel take online payments without talking to a bank directly. You create a payment, send the customer to its payment URL, and receive the result through a signed webhook.'
            ) }}</p>
            <pre class="flow"><code>Your server ──POST /api/v1/payments──▶ {{ config('app.name') }} ──▶ Bank (token)
Your server ◀── payment_url ─────────┘
Customer   ──▶ payment_url ──▶ Bank payment page
Bank       ──▶ {{ config('app.name') }} callback ──▶ Bank verify ──▶ paid / failed
{{ config('app.name') }} ──signed webhook──▶ your webhook URL
Customer   ◀── 303 redirect to your return_url?payment_id=..&amp;order_id=..&amp;status=..</code></pre>

            <h3>{{ $t('آدرس پایه', 'Base URL') }}</h3>
            <dl class="kv">
                <dt>Base URL</dt><dd><code>{{ $base }}</code></dd>
                <dt>API</dt><dd><code>{{ $base }}/api/v1</code></dd>
                @if($basePath !== '')<dt>{{ $t('مسیر امضا', 'Signed path') }}</dt><dd><code>{{ $basePath }}/api/v1/...</code></dd>@endif
            </dl>
            @if($basePath !== '')
                <div class="note">{!! $t(
                    'سرویس در پوشهٔ <code>'.e($basePath).'</code> نصب است؛ این پیشوند باید در مسیری که امضا می‌کنید هم باشد.',
                    'The service is installed under <code>'.e($basePath).'</code>; that prefix is part of the path you sign.'
                ) !!}</div>
            @endif

            <h3>{{ $t('اطلاعاتی که از مدیر سرویس می‌گیرید', 'What you receive from the operator') }}</h3>
            <div class="table-wrap"><table>
                <thead><tr><th>{{ $t('مورد', 'Item') }}</th><th>{{ $t('نمونه', 'Example') }}</th><th>{{ $t('کاربرد', 'Used for') }}</th></tr></thead>
                <tbody>
                <tr><td>Client ID</td><td><code>tkc_…</code> ({{ $t('۳۰ کاراکتر', '30 chars') }})</td><td>{!! $t('هدر <code>X-Client-Id</code>', 'The <code>X-Client-Id</code> header') !!}</td></tr>
                <tr><td>Client secret</td><td><code>tksk_…</code> ({{ $t('۶۹ کاراکتر', '69 chars') }})</td><td>{{ $t('امضای درخواست‌ها. فقط سمت سرور نگه دارید.', 'Signing requests. Keep it server-side only.') }}</td></tr>
                <tr><td>Webhook secret</td><td><code>whsec_…</code></td><td>{{ $t('بررسی امضای وب‌هوک‌ها', 'Verifying webhooks') }}</td></tr>
                </tbody>
            </table></div>
            <p>{{ $t(
                'شما هم آدرس وب‌هوک (POST، HTTPS) و آدرس بازگشت پیش‌فرض (HTTPS) خود را به مدیر سرویس می‌دهید. یک کلید برای همهٔ درگاه‌های شما کافی است.',
                'In return you give the operator your webhook URL (POST, HTTPS) and default return URL (HTTPS). One key pair covers all of your gateways.'
            ) }}</p>
            <div class="note warn">{{ $t(
                'secretها فقط یک بار نمایش داده می‌شوند. آن‌ها را رمزنگاری‌شده ذخیره کنید، هرگز لاگ نکنید و هرگز به مرورگر نفرستید.',
                'Secrets are shown once. Store them encrypted, never log them and never send them to a browser.'
            ) }}</div>
        </div></section>

        {{-- Auth --}}
        <section id="auth" class="card"><div class="card-body">
            <h2>{{ $toc['auth'] }}</h2>
            <p>{{ $t('هر درخواست به ‎/api/v1 این هدرها را دارد:', 'Every request to /api/v1 carries these headers:') }}</p>
            <div class="table-wrap"><table>
                <thead><tr><th>{{ $t('هدر', 'Header') }}</th><th>{{ $t('مقدار', 'Value') }}</th></tr></thead>
                <tbody>
                <tr><td><code>X-Client-Id</code></td><td>Client ID</td></tr>
                <tr><td><code>X-Timestamp</code></td><td>{{ $t('زمان یونیکس به ثانیه؛ اختلاف بیش از ۳۰۰ ثانیه رد می‌شود', 'Unix time in seconds; more than 300 s off is rejected') }}</td></tr>
                <tr><td><code>X-Nonce</code></td><td>{!! $t('یکتا برای هر درخواست، ۱۶ تا ۶۴ کاراکتر <code>[A-Za-z0-9_-]</code>', 'Unique per request, 16-64 chars of <code>[A-Za-z0-9_-]</code>') !!}</td></tr>
                <tr><td><code>X-Signature</code></td><td><code>hex(HMAC_SHA256(client_secret, canonical))</code></td></tr>
                <tr><td><code>Content-Type</code> / <code>Accept</code></td><td><code>application/json</code></td></tr>
                </tbody>
            </table></div>
            <pre><code>canonical = METHOD + "\n"
          + PATH + "\n"              # {{ $basePath }}/api/v1/payments  (+ "?" + query, if any)
          + X-Timestamp + "\n"
          + X-Nonce + "\n"
          + hex(SHA256(raw body))     # SHA256("") for GET</code></pre>
            <ul>
                <li>{{ $t('METHOD با حروف بزرگ است.', 'METHOD is uppercase.') }}</li>
                <li>{{ $t('دقیقاً همان بایت‌هایی را امضا کنید که می‌فرستید: JSON را یک بار بسازید، همان را امضا و ارسال کنید.', 'Sign the exact bytes you send: serialize the JSON once, sign that string, send that string.') }}</li>
                <li>{{ $t('برای هر درخواست (حتی تکرار) nonce جدید بسازید.', 'Use a fresh nonce for every request, retries included.') }}</li>
                <li>{{ $t('از HTTPS استفاده کنید، redirect را دنبال نکنید، timeout حدود ۳۰ ثانیه.', 'Use HTTPS, do not follow redirects, use a ~30 s timeout.') }}</li>
                <li>{!! $t('Referer، Origin و User-Agent هیچ نقشی در احراز هویت ندارند. ساعت سرور را با NTP تنظیم نگه دارید.', 'Referer, Origin and User-Agent play no part in authentication. Keep your server clock in sync (NTP).') !!}</li>
            </ul>

            <h3>{{ $t('بردارهای تست', 'Test vectors') }}</h3>
            <p>{{ $t('پیاده‌سازی شما باید دقیقاً همین امضاها را تولید کند (برای همین نصب محاسبه شده‌اند):', 'Your implementation must reproduce these exactly (computed for this installation):') }}</p>
            <pre><code>secret     = {{ $vectors['secret'] }}
timestamp  = {{ $vectors['timestamp'] }}
nonce      = {{ $vectors['nonce'] }}

POST {{ $vectors['post_path'] }}
body       = {{ $vectors['post_body'] }}
sha256     = {{ $vectors['post_body_hash'] }}
signature  = {{ $vectors['post_signature'] }}

GET {{ $vectors['get_path'] }}   (empty body)
signature  = {{ $vectors['get_signature'] }}</code></pre>
        </div></section>

        {{-- Create --}}
        <section id="create" class="card"><div class="card-body">
            <h2>{{ $toc['create'] }}</h2>
            <p><code>POST {{ $base }}/api/v1/payments</code></p>
            <div class="table-wrap"><table>
                <thead><tr><th>{{ $t('فیلد', 'Field') }}</th><th>{{ $t('الزامی', 'Required') }}</th><th>{{ $t('توضیح', 'Notes') }}</th></tr></thead>
                <tbody>
                <tr><td><code>order_id</code></td><td>{{ $t('بله', 'yes') }}</td><td>{!! $t('شمارهٔ سفارش شما، حداکثر ۱۰۰ کاراکتر <code>[A-Za-z0-9._:-]</code>، یکتا برای هر سفارش', 'Your order id, max 100 chars of <code>[A-Za-z0-9._:-]</code>, unique per order') !!}</td></tr>
                <tr><td><code>amount</code></td><td>{{ $t('بله', 'yes') }}</td><td>{{ $t('عدد صحیح (نه رشته، نه اعشاری). برای IRR به ریال، حداقل ', 'Integer (not a string, not a float). Rials for IRR, minimum ') }}{{ number_format($minIrr) }}</td></tr>
                <tr><td><code>currency</code></td><td>{{ $t('خیر', 'no') }}</td><td>{!! $t('<code>IRR</code> (ریال، پیش‌فرض) یا <code>IRT</code> (تومان)', '<code>IRR</code> (Rial, default) or <code>IRT</code> (Toman)') !!}</td></tr>
                <tr><td><code>description</code></td><td>{{ $t('خیر', 'no') }}</td><td>{{ $t('حداکثر ۵۰۰ کاراکتر', 'Max 500 chars') }}</td></tr>
                <tr><td><code>return_url</code></td><td>{{ $t('خیر', 'no') }}</td><td>{{ $t('HTTPS؛ پیش‌فرض: آدرس بازگشت ثبت‌شدهٔ سایت شما', 'HTTPS; defaults to your registered return URL') }}</td></tr>
                <tr><td><code>merchant_id</code></td><td>{{ $t('خیر', 'no') }}</td><td>{!! $t('درگاه انتخابی (<a href="#gateways">انتخاب درگاه</a>)؛ پیش‌فرض: درگاه پیش‌فرض شما', 'Chosen gateway (see <a href="#gateways">Choosing a gateway</a>); defaults to your default gateway') !!}</td></tr>
                <tr><td><code>customer</code></td><td>{{ $t('خیر (توصیه می‌شود)', 'no (recommended)') }}</td><td>{!! $t('<code>{"mobile","username","name"}</code> پرداخت‌کننده؛ برای پیگیری پشتیبانی', '<code>{"mobile","username","name"}</code> of the payer; lets support find the payment') !!}</td></tr>
                <tr><td><code>metadata</code></td><td>{{ $t('خیر', 'no') }}</td><td>{{ $t('شیء دلخواه، حداکثر ۲۰ کلید / ۴ کیلوبایت، بدون تغییر برگردانده می‌شود', 'Free-form object, max 20 keys / 4 KB, returned as-is') }}</td></tr>
                <tr><td><code>new_attempt</code></td><td>{{ $t('خیر', 'no') }}</td><td>{!! $t('<code>true</code> برای تلاش دوباره روی پرداخت ناموفق (پایین را ببینید)', '<code>true</code> to retry a failed payment (see below)') !!}</td></tr>
                </tbody>
            </table></div>
            <p>{!! $t(
                'هدر اختیاری <code>Idempotency-Key</code> (۸ تا ۲۵۵ کاراکتر، مثلاً <code>order-ORD-10001</code>) جلوی ساخت پرداخت تکراری را می‌گیرد. ارسال دوبارهٔ همان سفارش با همان پارامترها همان پرداخت را برمی‌گرداند (<code>200</code>)؛ با پارامترهای متفاوت خطای <code>409 ORDER_ALREADY_EXISTS</code> می‌گیرد.',
                'The optional <code>Idempotency-Key</code> header (8-255 chars, e.g. <code>order-ORD-10001</code>) prevents duplicates. Sending the same order with the same parameters returns the same payment (<code>200</code>); different parameters return <code>409 ORDER_ALREADY_EXISTS</code>.'
            ) !!}</p>
            <h3>{{ $t('پاسخ', 'Response') }} <span class="muted small">201 Created / 200 OK</span></h3>
            <pre><code>{{ $responseExample }}</code></pre>
            <p>{!! $t(
                'مشتری را با redirect به <code>payment_url</code> بفرستید. پرداخت پرداخت‌نشده بعد از '.$ttl.' دقیقه منقضی می‌شود.',
                'Redirect the customer to <code>payment_url</code>. Unpaid payments expire after '.$ttl.' minutes.'
            ) !!}</p>
            <h3>{{ $t('مبلغ و واحد پول', 'Money') }}</h3>
            <p>{{ $t(
                'مبلغ همیشه عدد صحیح به واحد currency است (IRR = ریال، IRT = تومان). سرویس هیچ‌وقت مبلغ را گرد نمی‌کند؛ اگر درگاه واحد دیگری بخواهد و تبدیل دقیق ممکن نباشد، خطای AMOUNT_NOT_CONVERTIBLE برمی‌گردد. پاسخ‌ها و وب‌هوک‌ها همیشه به همان واحد پرداخت‌اند.',
                'Amounts are integers in the unit of currency (IRR = Rial, IRT = Toman). Nothing is ever rounded: if a gateway needs another unit and the conversion is not exact, the attempt fails with AMOUNT_NOT_CONVERTIBLE. Responses and webhooks always use the payment\'s own currency.'
            ) }}</p>
        </div></section>

        {{-- Gateways --}}
        <section id="gateways" class="card"><div class="card-body">
            <h2>{{ $toc['gateways'] }}</h2>
            <p>{{ $t(
                'هر درگاه (سپهر، زرین‌پال، …) یک «مرچنت» زیر حساب شماست. همهٔ مرچنت‌ها از همان یک کلید، همان Webhook secret و همان آدرس وب‌هوک استفاده می‌کنند.',
                'Each gateway (Sepehr, ZarinPal, …) is a "merchant" under your account. All merchants share the same key pair, webhook secret and webhook URL.'
            ) }}</p>
            <ol>
                <li>{!! $t('<code>GET /api/v1/merchants</code> فهرست درگاه‌ها را می‌دهد (<code>merchant_id</code>، <code>name</code>، <code>provider</code>، <code>is_default</code>، <code>status</code>).', '<code>GET /api/v1/merchants</code> lists them (<code>merchant_id</code>, <code>name</code>, <code>provider</code>, <code>is_default</code>, <code>status</code>).') !!}</li>
                <li>{!! $t('<code>merchant_id</code> درگاه انتخابی را در ساخت پرداخت بفرستید؛ بدون آن درگاه پیش‌فرض استفاده می‌شود.', 'Send the chosen <code>merchant_id</code> when creating the payment; without it the default gateway is used.') !!}</li>
                <li>{{ $t('درگاه‌ها را در برنامهٔ خود دستی تایپ نکنید؛ یک دکمهٔ «به‌روزرسانی درگاه‌ها» بگذارید که این فهرست را بخواند. اضافه شدن درگاه جدید هیچ کلیدی را عوض نمی‌کند.', 'Do not hard-code gateways: add a "sync gateways" action that reads this list. Adding a gateway never changes any key.') }}</li>
            </ol>
            <h3><code>GET /api/v1/merchants</code> — {{ $t('پاسخ', 'response') }} <span class="muted small">200 OK</span></h3>
            <pre><code>{
  "data": [
    {
      "merchant_id": "mer_01m4c8x2n7k5q9w3e6r1t4y8u0",
      "name": "Sepehr",
      "provider": "sepehr",
      "status": "active",
      "is_default": true,
      "configured_credentials": ["terminal_id"],
      "created_at": "2026-10-01T09:12:00Z",
      "updated_at": "2026-10-01T09:12:00Z"
    },
    {
      "merchant_id": "mer_01m4d1a7b3c9d5e2f8g4h6j0k2",
      "name": "ZarinPal",
      "provider": "zarinpal",
      "status": "disabled",
      "is_default": false,
      "configured_credentials": ["merchant_identifier"],
      "created_at": "2026-10-05T11:40:00Z",
      "updated_at": "2026-10-06T08:02:00Z"
    }
  ]
}</code></pre>
            <ul>
                <li>{!! $t('فقط ردیف‌های <code>"status": "active"</code> را برای پرداخت نشان دهید.', 'Offer only rows with <code>"status": "active"</code>.') !!}</li>
                <li>{!! $t('<code>merchant_id</code> شناسهٔ پایدار است؛ آن را ذخیره کنید. <code>name</code> ممکن است در پنل مدیریت تغییر کند.', '<code>merchant_id</code> is the stable id; store it. <code>name</code> may be renamed by the operator.') !!}</li>
                <li>{!! $t('اطلاعات حساب درگاه هرگز برگردانده نمی‌شود؛ <code>configured_credentials</code> فقط نام فیلدهای تنظیم‌شده است.', 'Gateway credentials are never returned; <code>configured_credentials</code> only names the configured fields.') !!}</li>
            </ul>
            <h3>{{ $t('ساخت پرداخت با درگاه مشخص', 'Create a payment on a specific gateway') }}</h3>
            <pre><code>POST {{ $base }}/api/v1/payments
{"order_id":"INV-1001-1","amount":500000,"currency":"IRR","merchant_id":"mer_01m4c8x2n7k5q9w3e6r1t4y8u0"}</code></pre>
            <p>{!! $t('<code>merchant_id</code> ناشناخته یا غیرفعال خطای <code>422 MERCHANT_NOT_FOUND</code> می‌دهد.', 'An unknown or disabled <code>merchant_id</code> returns <code>422 MERCHANT_NOT_FOUND</code>.') !!}</p>
        </div></section>

        {{-- Return --}}
        <section id="return" class="card"><div class="card-body">
            <h2>{{ $toc['return'] }}</h2>
            <p>{!! $t(
                'بعد از پرداخت، مشتری با <code>303</code> به <code>return_url?payment_id=…&amp;order_id=…&amp;status=…</code> برمی‌گردد. پارامتر status فقط برای نمایش است؛ قبل از تحویل کالا وضعیت را با <code>GET /api/v1/payments/{payment_id}</code> یا وب‌هوک تایید کنید.',
                'After paying, the customer is sent (<code>303</code>) to <code>return_url?payment_id=…&amp;order_id=…&amp;status=…</code>. The status parameter is only a hint: confirm with <code>GET /api/v1/payments/{payment_id}</code> or the webhook before delivering anything.'
            ) !!}</p>
            <p>{!! $t(
                'اگر وضعیت <code>callback_received</code> یا <code>verifying</code> بود، «در حال تایید» نشان دهید؛ وب‌هوک نتیجه را می‌فرستد. مشتری باید VPN را خاموش کند، چون درگاه‌های شاپرک IP خارجی را رد می‌کنند.',
                'If the status is <code>callback_received</code> or <code>verifying</code>, show "being confirmed": the webhook will follow. Customers must turn VPNs off; Shaparak payment pages reject foreign IPs.'
            ) !!}</p>
        </div></section>

        {{-- Webhooks --}}
        <section id="webhooks" class="card"><div class="card-body">
            <h2>{{ $toc['webhooks'] }}</h2>
            <p>{{ $t('وب‌هوک منبع اصلی نتیجه است. به آدرس وب‌هوک شما POST با بدنهٔ JSON و این هدرها ارسال می‌شود:', 'The webhook is the source of truth. Your webhook URL receives a POST with a JSON body and these headers:') }}</p>
            <div class="table-wrap"><table>
                <thead><tr><th>{{ $t('هدر', 'Header') }}</th><th>{{ $t('معنی', 'Meaning') }}</th></tr></thead>
                <tbody>
                <tr><td><code>{{ $headers['event'] }}</code></td><td>{{ $t('نام رویداد', 'Event name') }}</td></tr>
                <tr><td><code>{{ $headers['delivery'] }}</code></td><td>{{ $t('شناسهٔ یکتای ارسال؛ برای حذف تکراری‌ها', 'Unique delivery id; use it to de-duplicate') }}</td></tr>
                <tr><td><code>{{ $headers['timestamp'] }}</code></td><td>{{ $t('زمان یونیکس به ثانیه', 'Unix seconds') }}</td></tr>
                <tr><td><code>{{ $headers['signature'] }}</code></td><td><code>hex(HMAC_SHA256(webhook_secret, timestamp + "." + raw_body))</code></td></tr>
                </tbody>
            </table></div>
            <pre><code>{{ $vectors['webhook_body'] }}</code></pre>
            <div class="table-wrap"><table>
                <thead><tr><th>{{ $t('رویداد', 'Event') }}</th><th>{{ $t('معنی', 'Meaning') }}</th></tr></thead>
                <tbody>
                <tr><td><code>payment.created</code></td><td>{{ $t('پرداخت ثبت شد', 'Payment recorded') }}</td></tr>
                <tr><td><code>payment.pending</code></td><td>{{ $t('توکن بانک گرفته شد؛ مشتری می‌تواند پرداخت کند', 'Bank token obtained; the customer can pay') }}</td></tr>
                <tr><td><code>payment.succeeded</code></td><td><strong>{{ $t('بانک تایید کرد — سفارش را تحویل دهید', 'Verified by the bank — deliver the order') }}</strong></td></tr>
                <tr><td><code>payment.failed</code></td><td>{{ $t('رد شد یا تایید ناموفق بود', 'Rejected, or verification failed') }}</td></tr>
                <tr><td><code>payment.expired</code></td><td>{{ $t('در مهلت '.$ttl.' دقیقه پرداخت نشد', 'Not paid within '.$ttl.' minutes') }}</td></tr>
                <tr><td><code>payment.cancelled</code></td><td>{{ $t('با API لغو شد', 'Cancelled via the API') }}</td></tr>
                </tbody>
            </table></div>
            <ol>
                <li>{{ $t('بدنهٔ خام را قبل از parse کردن بخوانید؛ امضا روی بایت‌های خام است.', 'Read the raw body before parsing; the signature covers the raw bytes.') }}</li>
                <li>{{ $t('اگر اختلاف زمان بیش از ۳۰۰ ثانیه است یا امضا (با مقایسهٔ زمان‌ثابت) نمی‌خواند، 401 برگردانید.', 'Return 401 if the timestamp is more than 300 s off or the signature (constant-time compare) does not match.') }}</li>
                <li>{{ $t('ارسال‌ها ممکن است تکرار شوند؛ شناسهٔ ارسال را ذخیره و تکراری‌ها را رد کنید.', 'Deliveries can repeat: store delivery ids and skip duplicates.') }}</li>
                <li>{{ $t('سفارش را با order_id پیدا کنید و payment_id، مبلغ و واحد پول را مقایسه کنید.', 'Find the order by order_id and check payment_id, amount and currency.') }}</li>
                <li>{{ $t('علامت «پرداخت‌شده» را یک بار و به‌صورت امن در برابر هم‌زمانی بزنید؛ سفارش پرداخت‌شده با رویداد بعدی برنمی‌گردد.', 'Mark the order paid once, concurrency-safely; a later event never un-pays it.') }}</li>
                <li>{{ $t('سریع (زیر ۱۰ ثانیه) کد 2xx برگردانید. پاسخ غیر 2xx تا ۸ بار با فاصلهٔ افزایشی دوباره ارسال می‌شود.', 'Answer 2xx quickly (under 10 s). Non-2xx answers are retried with exponential backoff, up to 8 times.') }}</li>
                <li>{{ $t('آدرس وب‌هوک نباید لاگین یا CSRF بخواهد.', 'The webhook URL must not require a login or CSRF token.') }}</li>
            </ol>
            <h3>{{ $t('بردار تست وب‌هوک', 'Webhook test vector') }}</h3>
            <pre><code>secret     = {{ $vectors['webhook_secret'] }}
timestamp  = {{ $vectors['webhook_timestamp'] }}
body       = (the JSON above, exact bytes)
signature  = {{ $vectors['webhook_signature'] }}</code></pre>
        </div></section>

        {{-- Endpoints --}}
        <section id="endpoints" class="card"><div class="card-body">
            <h2>{{ $toc['endpoints'] }}</h2>
            <div class="table-wrap"><table>
                <thead><tr><th>Endpoint</th><th>{{ $t('کاربرد', 'Purpose') }}</th></tr></thead>
                <tbody>
                <tr><td><code>GET /api/v1/payments/{payment_id}</code></td><td>{{ $t('وضعیت قطعی پرداخت', 'Authoritative payment status') }}</td></tr>
                <tr><td><code>POST /api/v1/payments/{payment_id}/verify</code></td><td>{{ $t('تایید دوباره از بانک (تکرار آن بی‌خطر است)', 'Re-verify with the bank (safe to repeat)') }}</td></tr>
                <tr><td><code>POST /api/v1/payments/{payment_id}/cancel</code></td><td>{{ $t('لغو پرداخت پرداخت‌نشده', 'Cancel an unpaid payment') }}</td></tr>
                <tr><td><code>GET /api/v1/merchants</code></td><td>{{ $t('فهرست درگاه‌های شما (برای تست اتصال هم مناسب است)', 'Your gateways (also a good connection test)') }}</td></tr>
                </tbody>
            </table></div>
            <h3>{{ $t('وضعیت‌ها', 'Statuses') }}</h3>
            <p><code>created</code> → <code>pending</code> → <code>redirected</code> → <code>callback_received</code> → <code>verifying</code> → <code>paid</code> &nbsp;|&nbsp; <code>failed</code> · <code>cancelled</code> · <code>expired</code></p>
            <h3>{{ $t('تلاش دوباره', 'Retrying') }}</h3>
            <p>{!! $t(
                'برای پرداخت <code>failed</code> همان <code>order_id</code>، <code>amount</code> و <code>currency</code> را با <code>"new_attempt": true</code> بفرستید (می‌توانید <code>merchant_id</code> درگاه دیگری را هم بفرستید). برای پرداخت منقضی یا لغوشده یک <code>order_id</code> جدید بسازید، مثلاً <code>ORD-10001-2</code>.',
                'For a <code>failed</code> payment send the same <code>order_id</code>, <code>amount</code> and <code>currency</code> with <code>"new_attempt": true</code> (optionally with another gateway\'s <code>merchant_id</code>). For an expired or cancelled payment use a new <code>order_id</code>, e.g. <code>ORD-10001-2</code>.'
            ) !!}</p>
        </div></section>

        {{-- Errors --}}
        <section id="errors" class="card"><div class="card-body">
            <h2>{{ $toc['errors'] }}</h2>
            <pre><code>{ "error": { "code": "VALIDATION_ERROR", "message": "...", "request_id": "req_01...", "details": { ... } } }</code></pre>
            <p>{!! $t('<code>code</code> و <code>request_id</code> را لاگ کنید (نه secretها) و هنگام تماس با پشتیبانی بفرستید.', 'Log <code>code</code> and <code>request_id</code> (never secrets) and quote them to support.') !!}</p>
            <div class="table-wrap"><table>
                <thead><tr><th>HTTP</th><th>Code</th><th>{{ $t('علت / اقدام', 'Cause / action') }}</th></tr></thead>
                <tbody>
                @foreach([
                    ['401', 'AUTH_INVALID_SIGNATURE', 'کلید/secret/مسیر/بدنه اشتباه. طول‌ها (۳۰/۶۹) و پیشوند مسیر را بررسی کنید.', 'Wrong key, secret, path or body bytes. Check lengths (30/69) and the path prefix.'],
                    ['401', 'AUTH_TIMESTAMP_EXPIRED', 'ساعت سرور شما بیش از ۳۰۰ ثانیه اختلاف دارد.', 'Your clock is more than 300 s off.'],
                    ['401', 'AUTH_NONCE_REPLAYED', 'nonce تکراری است.', 'Nonce reused.'],
                    ['401', 'AUTH_REQUIRED', 'هدرهای احراز هویت ارسال نشده‌اند.', 'Authentication headers missing.'],
                    ['403', 'AUTH_CLIENT_DISABLED', 'کلید باطل شده یا حساب غیرفعال است.', 'Key revoked or account disabled.'],
                    ['404', 'PAYMENT_NOT_FOUND', 'پرداخت وجود ندارد یا متعلق به شما نیست.', 'Unknown payment, or not yours.'],
                    ['409', 'ORDER_ALREADY_EXISTS', 'همان order_id با پارامترهای متفاوت.', 'Same order_id with different parameters.'],
                    ['409', 'NEW_ATTEMPT_NOT_ALLOWED', 'new_attempt فقط برای پرداخت failed.', 'new_attempt only works on failed payments.'],
                    ['422', 'IDEMPOTENCY_KEY_REUSED', 'همان Idempotency-Key با بدنهٔ متفاوت.', 'Same Idempotency-Key with a different body.'],
                    ['422', 'VALIDATION_ERROR', 'جزئیات در error.details.', 'See error.details.'],
                    ['422', 'MERCHANT_NOT_FOUND', 'درگاه پیدا نشد یا فعال نیست.', 'Gateway not found or not active.'],
                    ['422', 'PROVIDER_UNAVAILABLE', 'درگاه موقتاً در دسترس نیست.', 'Gateway currently unavailable.'],
                    ['429', 'RATE_LIMITED', 'کمی صبر کنید و دوباره تلاش کنید.', 'Back off and retry.'],
                    ['5xx', 'INTERNAL_ERROR', 'بعداً با همان order_id دوباره تلاش کنید (بی‌خطر است).', 'Retry later with the same order_id (safe).'],
                ] as [$http, $code, $faText, $enText])
                    <tr><td>{{ $http }}</td><td><code>{{ $code }}</code></td><td>{{ $t($faText, $enText) }}</td></tr>
                @endforeach
                </tbody>
            </table></div>
        </div></section>

        {{-- Samples --}}
        <section id="samples" class="card"><div class="card-body">
            <h2>{{ $toc['samples'] }}</h2>
            <p>{{ $t('نمونه‌ها با آدرس همین سرویس آماده‌اند. کلیدها را از متغیرهای محیطی بخوانید.', 'Samples use this service\'s base URL. Read keys from environment variables.') }}</p>
            <details open><summary>PHP — {{ $t('ساخت پرداخت و بررسی وضعیت', 'create a payment and check status') }}</summary><pre><code>{{ $samples['php'] }}</code></pre></details>
            <details><summary>PHP — {{ $t('دریافت وب‌هوک', 'receive webhooks') }}</summary><pre><code>{{ $samples['php_webhook'] }}</code></pre></details>
            <details><summary>Node.js</summary><pre><code>{{ $samples['node'] }}</code></pre></details>
            <details><summary>Python</summary><pre><code>{{ $samples['python'] }}</code></pre></details>
        </div></section>

        {{-- Checklist --}}
        <section id="checklist" class="card"><div class="card-body">
            <h2>{{ $toc['checklist'] }}</h2>
            <ol>
                <li>{{ $t('تست‌های واحد شما هر سه بردار تست را بازتولید می‌کنند.', 'Unit tests reproduce all three test vectors.') }}</li>
                <li>{!! $t('<code>GET /api/v1/merchants</code> با کلیدهای واقعی 200 برمی‌گرداند.', '<code>GET /api/v1/merchants</code> returns 200 with the real keys.') !!}</li>
                <li>{{ $t('دوبار کلیک روی «پرداخت» دو پرداخت نمی‌سازد (order_id ثابت + Idempotency-Key).', 'A double click on "Pay" does not create two payments (stable order_id + Idempotency-Key).') }}</li>
                <li>{{ $t('صفحهٔ بازگشت وضعیت را از API می‌گیرد، نه از آدرس.', 'The return page reads the status from the API, not from the URL.') }}</li>
                <li>{{ $t('وب‌هوک امضای نامعتبر را با 401 رد می‌کند و تکراری‌ها را نادیده می‌گیرد.', 'The webhook rejects bad signatures with 401 and ignores duplicates.') }}</li>
                <li>{{ $t('یک پرداخت واقعی با مبلغ کم انجام و هم با بازگشت و هم با وب‌هوک تایید شده است.', 'One real low-amount payment was completed and confirmed by both the return page and the webhook.') }}</li>
            </ol>
        </div></section>
    </main>
</div>
</body>
</html>
