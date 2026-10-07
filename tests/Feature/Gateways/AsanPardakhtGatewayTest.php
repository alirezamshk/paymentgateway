<?php

namespace Tests\Feature\Gateways;

use App\Enums\PaymentStatus;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class AsanPardakhtGatewayTest extends GatewayTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpProvider('asanpardakht', ['merchant_identifier' => '270', 'username' => 'apuser', 'password' => 'appass']);
    }

    public function test_token_tranresult_verify_and_settlement(): void
    {
        Http::fake(function (Request $r) {
            return match (true) {
                str_ends_with($r->url(), '/v1/Token') => Http::response('"tok-asan"', 200),
                str_contains($r->url(), '/v1/TranResult') => Http::response([
                    'cardNumber' => '603799******1234', 'rrn' => '112233445566', 'refID' => 'R1',
                    'amount' => '500000', 'payGateTranID' => '9988776655', 'salesOrderID' => '1',
                ]),
                str_ends_with($r->url(), '/v1/Verify') => Http::response('', 200),
                str_ends_with($r->url(), '/v1/Settlement') => Http::response('', 200),
                default => Http::response('', 200),
            };
        });

        $payment = $this->createPayment();
        $this->assertSame(['RefId' => 'tok-asan'], $payment->latestAttempt->redirect_payload['fields']);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/v1/Token')
            && $r->header('usr')[0] === 'apuser' && $r->header('pwd')[0] === 'appass'
            && $r['merchantConfigurationId'] === 270 && $r['amountInRials'] === 500000
            && $r['localInvoiceId'] === $payment->latestAttempt->psp_invoice_id);

        $this->post("/api/v1/gateways/asanpardakht/callback/{$payment->public_id}")->assertRedirectContains('status=paid');

        $payment->refresh();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame('112233445566', $payment->reference_number);
        $this->assertNotNull($payment->settled_at);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/v1/Verify') && $r['payGateTranId'] === 9988776655);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/v1/Settlement') && $r['payGateTranId'] === 9988776655);

        // usr/pwd headers are never stored.
        $this->assertStringNotContainsString('appass', json_encode($payment->latestAttempt->toArray()));
    }

    public function test_no_transaction_found_fails(): void
    {
        Http::fake(fn (Request $r) => match (true) {
            str_ends_with($r->url(), '/v1/Token') => Http::response('"tok"', 200),
            str_contains($r->url(), '/v1/TranResult') => Http::response('', 472),
            default => Http::response('', 200),
        });

        $payment = $this->createPayment();
        $this->post("/api/v1/gateways/asanpardakht/callback/{$payment->public_id}");

        $this->assertStatus($payment, PaymentStatus::Failed);
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/v1/Verify'));
    }

    public function test_failed_settlement_keeps_payment_paid_and_is_retried(): void
    {
        $settleOk = false;
        Http::fake(function (Request $r) use (&$settleOk) {
            return match (true) {
                str_ends_with($r->url(), '/v1/Token') => Http::response('"tok"', 200),
                str_contains($r->url(), '/v1/TranResult') => Http::response(['amount' => '500000', 'payGateTranID' => '1', 'rrn' => '2']),
                str_ends_with($r->url(), '/v1/Settlement') => Http::response('', $settleOk ? 200 : 400),
                default => Http::response('', 200),
            };
        });

        $payment = $this->createPayment();
        $this->post("/api/v1/gateways/asanpardakht/callback/{$payment->public_id}");
        $this->assertStatus($payment, PaymentStatus::Paid);
        $this->assertNull($payment->fresh()->settled_at);

        $settleOk = true;
        $this->artisan('payments:reconcile')->assertSuccessful();
        $this->assertNotNull($payment->fresh()->settled_at);
    }

    public function test_tranresult_timeout_leaves_payment_retryable(): void
    {
        Http::fake(fn (Request $r) => match (true) {
            str_ends_with($r->url(), '/v1/Token') => Http::response('"tok"', 200),
            str_contains($r->url(), '/v1/TranResult') => Http::response('', 503),
            default => Http::response('', 200),
        });

        $payment = $this->createPayment();
        $this->post("/api/v1/gateways/asanpardakht/callback/{$payment->public_id}");

        $this->assertStatus($payment, PaymentStatus::CallbackReceived);
    }
}
