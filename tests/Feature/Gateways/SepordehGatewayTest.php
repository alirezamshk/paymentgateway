<?php

namespace Tests\Feature\Gateways;

use App\Enums\PaymentStatus;
use App\Models\GatewayProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class SepordehGatewayTest extends GatewayTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpProvider('sepordeh', ['merchant_identifier' => 'sep-merchant-key']);
    }

    public function test_create_and_verify_with_toman_conversion(): void
    {
        Http::fake([
            'sepordeh.com/merchant/invoices/add' => Http::response(['status' => 200, 'information' => ['invoice_id' => 'INV123']]),
            'sepordeh.com/merchant/invoices/verify' => Http::response(['status' => 200, 'information' => ['invoice_id' => 'INV123', 'card' => '6037991234567890', 'amount' => 50000]]),
            '*.example.com/*' => Http::response('', 200),
        ]);

        $payment = $this->createPayment(['amount' => 500000, 'currency' => 'IRR']);
        $this->assertSame('https://sepordeh.com/merchant/invoices/pay/id:INV123', $payment->latestAttempt->redirect_payload['url']);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'invoices/add') && (int) $r['amount'] === 50000 && $r['merchant'] === 'sep-merchant-key');

        $this->get("/api/v1/gateways/sepordeh/callback/{$payment->public_id}?authority=INV123")->assertRedirectContains('status=paid');

        $payment->refresh();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame('603799******7890', $payment->card_mask);
    }

    public function test_irr_amount_not_divisible_by_ten_is_rejected_not_rounded(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $payment = $this->createPayment(['amount' => 500005, 'currency' => 'IRR']);

        $this->assertStatus($payment, PaymentStatus::Failed);
        $this->assertSame('AMOUNT_NOT_CONVERTIBLE', $payment->latestAttempt->error_code);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'sepordeh.com'));
    }

    public function test_verify_rejection(): void
    {
        Http::fake([
            'sepordeh.com/merchant/invoices/add' => Http::response(['status' => 200, 'information' => ['invoice_id' => 'INV9']]),
            'sepordeh.com/merchant/invoices/verify' => Http::response(['status' => 400, 'message' => 'not paid']),
            '*.example.com/*' => Http::response('', 200),
        ]);

        $payment = $this->createPayment();
        $this->get("/api/v1/gateways/sepordeh/callback/{$payment->public_id}?authority=INV9");

        $this->assertStatus($payment, PaymentStatus::Failed);
    }

    public function test_direct_mode_and_case_insensitive_response(): void
    {
        GatewayProvider::where('code', 'sepordeh')->update(['config' => ['amount_currency' => 'IRT', 'direct' => true]]);
        Http::fake([
            'sepordeh.com/merchant/invoices/add' => Http::response(['Status' => 200, 'Information' => ['Invoice_ID' => 'INV7']]),
            'sepordeh.com/merchant/invoices/verify' => Http::response(['Status' => 200, 'Information' => ['Invoice_ID' => 'INV7', 'Card' => '603799******1111']]),
            '*.example.com/*' => Http::response('', 200),
        ]);

        $payment = $this->createPayment();
        $this->assertSame('https://sepordeh.com/merchant/invoices/pay/automatic:true/id:INV7', $payment->latestAttempt->redirect_payload['url']);

        $this->get("/api/v1/gateways/sepordeh/callback/{$payment->public_id}?authority=INV7")->assertRedirectContains('status=paid');
        $this->assertSame('603799******1111', $payment->fresh()->card_mask);
    }
}
