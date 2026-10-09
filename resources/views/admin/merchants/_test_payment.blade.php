{{-- Real payment through this merchant, for the admin to try it end to end. --}}
<form class="inline" method="POST" action="{{ route('admin.merchants.test-payment', $merchant) }}" title="{{ __('Pay a small real amount through this merchant. No webhook, not counted in settlements or reports.') }}">
    @csrf
    <input type="number" name="amount" value="50000" min="{{ config('payments.amount_limits.IRT.min') }}" step="1" required dir="ltr" style="width:96px;padding:4px 6px" aria-label="{{ __('Amount (Toman)') }}">
    <button class="btn secondary sm">{{ __('Test payment') }}</button>
</form>
