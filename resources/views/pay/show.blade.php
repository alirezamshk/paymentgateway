@extends('pay.layout')
@php
    $labels = [
        'created' => 'ایجاد شده', 'pending' => 'در انتظار پرداخت', 'redirected' => 'در انتظار پرداخت',
        'callback_received' => 'در حال بررسی', 'verifying' => 'در حال تایید', 'paid' => 'پرداخت موفق',
        'failed' => 'ناموفق', 'cancelled' => 'لغو شده', 'expired' => 'منقضی شده',
    ];
    $currencyLabel = $payment->currency->value === 'IRT' ? 'تومان' : 'ریال';
@endphp
@section('title', 'پرداخت')
@section('content')
    @if($redirect)
        {{-- Payable: hand the customer to the PSP immediately. The page itself must be served from
             this domain (PSPs such as Sepehr check the Referer) and POST-based PSPs need a form. --}}
        <div class="redirecting">
            <div class="sparkle" id="sparkle" aria-hidden="true">✻</div>
            <p>در حال انتقال به درگاه بانک...</p>
        </div>
        <form id="psp-form" method="{{ strtoupper($redirect->method) === 'POST' ? 'POST' : 'GET' }}" action="{{ $redirect->url }}">
            {{-- Fields are sent as form data (POST) or as the query string (GET). --}}
            @foreach($redirect->fields as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <noscript><button class="btn" type="submit">ادامه و انتقال به درگاه بانک</button></noscript>
            <button class="btn" id="psp-fallback" type="submit" hidden>اگر منتقل نشدید، اینجا کلیک کنید</button>
        </form>
    @else
        <h1>پرداخت به {{ $payment->client->name }}</h1>
        <dl>
            <dt>مبلغ</dt><dd>{{ number_format($payment->amount) }} {{ $currencyLabel }}</dd>
            @if($payment->description)<dt>توضیحات</dt><dd>{{ $payment->description }}</dd>@endif
            <dt>شماره سفارش</dt><dd>{{ $payment->order_id }}</dd>
            <dt>وضعیت</dt><dd class="status-{{ $payment->status->value }}">{{ $labels[$payment->status->value] ?? $payment->status->value }}</dd>
            @if($payment->reference_number)<dt>کد پیگیری</dt><dd>{{ $payment->reference_number }}</dd>@endif
        </dl>
        @if($payment->return_url && $payment->status->isFinal())
            <a class="btn" href="{{ $payment->return_url }}">بازگشت به سایت</a>
        @endif
    @endif
@endsection
@section('scripts')
    @if($redirect && $nonce)
        <script nonce="{{ $nonce }}">
            (function () {
                var frames = ['·', '✢', '✳', '✶', '✻', '✽', '✻', '✶', '✳', '✢'], i = 0, el = document.getElementById('sparkle');
                if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    setInterval(function () { el.textContent = frames[i = (i + 1) % frames.length]; }, 110);
                }
            })();
            document.getElementById('psp-form').submit();
            setTimeout(function () { document.getElementById('psp-fallback').hidden = false; }, 3000);
        </script>
    @endif
@endsection
