<?php

namespace Tests\Feature\Gateways;

use App\Enums\PaymentStatus;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class ZarinPalGatewayTest extends GatewayTestCase
{
    private const MERCHANT = '1344b5d4-0048-11e8-94db-005056a205be';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpProvider('zarinpal', ['merchant_identifier' => self::MERCHANT]);
    }

    public function test_create_and_verify(): void
    {
        Http::fake([
            'sandbox.zarinpal.com/pg/v4/payment/request.json' => Http::response(['data' => ['code' => 100, 'message' => 'Success', 'authority' => 'A0000000000000000000000000000wwOGYpd'], 'errors' => []]),
            'sandbox.zarinpal.com/pg/v4/payment/verify.json' => Http::response(['data' => ['code' => 100, 'ref_id' => 201, 'card_pan' => '502229******5995', 'fee' => 0], 'errors' => []]),
            '*.example.com/*' => Http::response('', 200),
        ]);

        $payment = $this->createPayment();
        $this->assertStatus($payment, PaymentStatus::Pending);
        $this->assertSame('https://sandbox.zarinpal.com/pg/StartPay/A0000000000000000000000000000wwOGYpd', $payment->latestAttempt->redirect_payload['url']);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'request.json')
            && $r['merchant_id'] === self::MERCHANT
            && $r['amount'] === 500000
            && $r['currency'] === 'IRR'
            && $r['callback_url'] === "https://pay.example.test/api/v1/gateways/zarinpal/callback/{$payment->public_id}");

        // Merchant id is not persisted in plaintext payload logs.
        $this->assertSame('[REDACTED]', $payment->latestAttempt->request_payload['merchant_id']);

        $this->get("/api/v1/gateways/zarinpal/callback/{$payment->public_id}?Authority=A0000000000000000000000000000wwOGYpd&Status=OK")
            ->assertRedirectContains('status=paid');

        $payment->refresh();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame('201', $payment->reference_number);
        $this->assertSame('502229******5995', $payment->card_mask);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'verify.json') && $r['amount'] === 500000 && $r['authority'] === 'A0000000000000000000000000000wwOGYpd');
    }

    public function test_verify_failure_marks_failed(): void
    {
        Http::fake([
            'sandbox.zarinpal.com/pg/v4/payment/request.json' => Http::response(['data' => ['code' => 100, 'authority' => 'A1'], 'errors' => []]),
            'sandbox.zarinpal.com/pg/v4/payment/verify.json' => Http::response(['data' => [], 'errors' => ['code' => -51, 'message' => 'Session is not valid']], 422),
            '*.example.com/*' => Http::response('', 200),
        ]);

        $payment = $this->createPayment();
        $this->get("/api/v1/gateways/zarinpal/callback/{$payment->public_id}?Authority=A1&Status=OK");

        $this->assertStatus($payment, PaymentStatus::Failed);
        $this->assertSame('ZARINPAL_-51', $payment->latestAttempt->fresh()->error_code);
    }

    public function test_request_rejection_and_psp_outage(): void
    {
        Http::fake([
            'sandbox.zarinpal.com/*' => Http::sequence()
                ->push(['data' => [], 'errors' => ['code' => -9, 'message' => 'Validation error']], 422)
                ->push('upstream says no', 502)
                ->push('<html><head><title>Error</title><style>body{margin:0}</style></head><body><h2>500 - Internal server error.</h2><h3>There is a problem.</h3></body></html>', 507),
            '*.example.com/*' => Http::response('', 200),
        ]);

        $payment = $this->createPayment();
        $this->assertStatus($payment, PaymentStatus::Failed);
        $this->assertSame('ZARINPAL_-9', $payment->latestAttempt->error_code);

        $second = $this->createPayment();
        $this->assertStatus($second, PaymentStatus::Failed);
        $this->assertSame('GATEWAY_UNAVAILABLE', $second->latestAttempt->error_code);
        // The PSP's answer is kept for diagnosis.
        $this->assertSame(['status' => 502, 'body' => 'upstream says no'], $second->latestAttempt->response_payload);

        // HTML error pages are reduced to their text.
        $third = $this->createPayment();
        $this->assertSame(['status' => 507, 'body' => '500 - Internal server error. There is a problem.'], $third->latestAttempt->response_payload);
    }

    public function test_mismatched_authority_is_not_verified(): void
    {
        Http::fake([
            'sandbox.zarinpal.com/pg/v4/payment/request.json' => Http::response(['data' => ['code' => 100, 'authority' => 'A1'], 'errors' => []]),
            '*.example.com/*' => Http::response('', 200),
        ]);

        $payment = $this->createPayment();
        $this->get("/api/v1/gateways/zarinpal/callback/{$payment->public_id}?Authority=A2&Status=OK");

        $this->assertStatus($payment, PaymentStatus::Pending);
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), 'verify.json'));
    }
}
