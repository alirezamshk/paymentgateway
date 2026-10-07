<?php

namespace Tests\Feature\Gateways;

use App\Enums\PaymentStatus;
use App\Models\Merchant;
use App\Models\Payment;
use Tests\TestCase;

abstract class GatewayTestCase extends TestCase
{
    protected array $auth;

    protected Merchant $merchant;

    protected function setUpProvider(string $provider, array $credentials): void
    {
        $this->auth = $this->makeClient();
        $this->merchant = $this->makeMerchant($this->auth['client'], $provider, $credentials);
    }

    protected function createPayment(array $overrides = []): Payment
    {
        $id = $this->signed($this->auth, 'POST', '/api/v1/payments', $this->paymentBody($overrides))->assertSuccessful()->json('payment_id');

        return Payment::where('public_id', $id)->firstOrFail();
    }

    protected function assertStatus(Payment $payment, PaymentStatus $status): void
    {
        $this->assertSame($status, $payment->fresh()->status);
    }
}
