<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Client-side merchant management is opt-in (operator-managed by default).
        config(['payments.client_merchant_write' => true]);
    }

    public function test_client_cannot_access_another_clients_payment(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        $a = $this->makeClient('site-a');
        $b = $this->makeClient('site-b');
        $this->makeMerchant($a['client']);
        $this->makeMerchant($b['client']);

        $paymentA = $this->signed($a, 'POST', '/api/v1/payments', $this->paymentBody())->json('payment_id');

        $this->signed($b, 'GET', "/api/v1/payments/{$paymentA}")->assertNotFound()->assertJsonPath('error.code', 'PAYMENT_NOT_FOUND');
        $this->signed($b, 'POST', "/api/v1/payments/{$paymentA}/verify")->assertNotFound();
        $this->signed($b, 'POST', "/api/v1/payments/{$paymentA}/cancel")->assertNotFound();
        $this->signed($a, 'GET', "/api/v1/payments/{$paymentA}")->assertOk();
    }

    public function test_client_only_sees_and_edits_own_merchants(): void
    {
        $a = $this->makeClient('site-a');
        $b = $this->makeClient('site-b');
        $merchantA = $this->makeMerchant($a['client']);
        $merchantB = $this->makeMerchant($b['client']);

        $list = $this->signed($a, 'GET', '/api/v1/merchants')->assertOk()->json();
        $this->assertSame([$merchantA->public_id], array_column($list['data'], 'merchant_id'));

        $this->signed($a, 'GET', "/api/v1/merchants/{$merchantB->public_id}")->assertNotFound();
        $this->signed($a, 'PATCH', "/api/v1/merchants/{$merchantB->public_id}", ['name' => 'hijack'])->assertNotFound();
        $this->assertNotSame('hijack', $merchantB->fresh()->name);
    }

    public function test_client_id_in_body_is_ignored(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        $a = $this->makeClient('site-a');
        $b = $this->makeClient('site-b');
        $this->makeMerchant($a['client']);
        $this->makeMerchant($b['client']);

        $id = $this->signed($a, 'POST', '/api/v1/payments', $this->paymentBody(['client_id' => $b['client']->id]))->json('payment_id');

        $this->signed($a, 'GET', "/api/v1/payments/{$id}")->assertOk();
        $this->signed($b, 'GET', "/api/v1/payments/{$id}")->assertNotFound();
    }
}
