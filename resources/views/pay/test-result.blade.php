@extends('pay.layout')
@section('title', __('Test payment result'))
@section('content')
    @php
        $status = $payment->status->value;
        $attempt = $payment->attempts()->latest('attempt_number')->first();
    @endphp
    <h1>{{ __('Test payment result') }}</h1>
    <dl>
        <dt>{{ __('Status') }}</dt><dd class="status-{{ $status }}">{{ __($status) }}</dd>
        <dt>{{ __('Merchant') }}</dt><dd>{{ $payment->merchant->name }} <span class="muted">({{ $payment->provider->name }})</span></dd>
        <dt>{{ __('Amount') }}</dt><dd>{{ number_format($payment->amount) }} {{ __($payment->currency->value) }}</dd>
        @if($payment->reference_number)<dt>{{ __('Reference') }}</dt><dd dir="ltr">{{ $payment->reference_number }}</dd>@endif
        @if($status !== 'paid' && $attempt?->error_code)<dt>{{ __('Error') }}</dt><dd dir="ltr">{{ $attempt->error_code }} {{ $attempt->error_message }}</dd>@endif
    </dl>
    @if(in_array($status, ['callback_received', 'verifying'], true))
        <p class="muted">{{ __('The payment is being confirmed with the bank. Refresh this page in a moment.') }}</p>
    @endif
    <p class="muted">{{ __('This was an admin test: no webhook was sent to the site and it is not counted in settlements or reports.') }}</p>
    <a class="btn" style="text-align:center;text-decoration:none" href="{{ route('admin.payments.show', $payment) }}">{{ __('View in admin panel') }}</a>
@endsection
