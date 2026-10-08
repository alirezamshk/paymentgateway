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
    <h1>پرداخت به {{ $payment->client->name }}</h1>
    <dl>
        <dt>مبلغ</dt><dd>{{ number_format($payment->amount) }} {{ $currencyLabel }}</dd>
        @if($payment->description)<dt>توضیحات</dt><dd>{{ $payment->description }}</dd>@endif
        <dt>شماره سفارش</dt><dd>{{ $payment->order_id }}</dd>
        <dt>وضعیت</dt><dd class="status-{{ $payment->status->value }}">{{ $labels[$payment->status->value] ?? $payment->status->value }}</dd>
        @if($payment->reference_number)<dt>کد پیگیری</dt><dd>{{ $payment->reference_number }}</dd>@endif
    </dl>

    @if($redirect)
        <form id="psp-form" method="{{ strtoupper($redirect->method) === 'POST' ? 'POST' : 'GET' }}" action="{{ $redirect->url }}">
            {{-- Fields are sent as form data (POST) or as the query string (GET). --}}
            @foreach($redirect->fields as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <button class="btn" type="submit">ادامه و انتقال به درگاه بانک</button>
        </form>
        <p class="muted">در حال انتقال به درگاه پرداخت...</p>
    @elseif($payment->return_url && $payment->status->isFinal())
        <a class="btn" href="{{ $payment->return_url }}">بازگشت به سایت</a>
    @endif
@endsection
@section('scripts')
    @if($redirect && $nonce)
        <script nonce="{{ $nonce }}">setTimeout(function () { document.getElementById('psp-form').submit(); }, 1500);</script>
    @endif
@endsection
