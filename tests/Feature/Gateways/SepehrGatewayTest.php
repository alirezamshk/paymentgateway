<?php

namespace Tests\Feature\Gateways;

use App\Enums\PaymentStatus;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class SepehrGatewayTest extends GatewayTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpProvider('sepehr', ['terminal_identifier' => '98765432']);
    }

    private function callbackData($payment, array $overrides = []): array
    {
        return array_merge([
            'respcode' => '0', 'respmsg' => 'OK', 'amount' => '500000',
            'invoiceid' => (string) $payment->latestAttempt->psp_invoice_id, 'terminalid' => '98765432',
            'tracenumber' => '123456', 'rrn' => '987654321012', 'digitalreceipt' => 'DR-ABC',
            'cardnumber' => '603799******1234',
        ], $overrides);
    }

    public function test_create_and_advice(): void
    {
        Http::fake([
            'sepehr.shaparak.ir:8081/V1/PeymentApi/GetToken' => Http::response(['Status' => 0, 'Accesstoken' => 'tok-123']),
            'sepehr.shaparak.ir:8081/V1/PeymentApi/Advice' => Http::response(['Status' => 'Ok', 'ReturnId' => '500000', 'Message' => 'ok']),
            '*.example.com/*' => Http::response('', 200),
        ]);

        $payment = $this->createPayment();
        $redirect = $payment->latestAttempt->redirect_payload;
        $this->assertSame('POST', $redirect['method']);
        $this->assertSame(['token' => 'tok-123', 'terminalID' => '98765432'], $redirect['fields']);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'GetToken') && $r->isForm()
            && $r['Amount'] == 500000 && $r['TerminalID'] === '98765432' && $r['InvoiceID'] === (string) $payment->latestAttempt->psp_invoice_id);

        $this->post("/api/v1/gateways/sepehr/callback/{$payment->public_id}", $this->callbackData($payment))
            ->assertRedirectContains('status=paid');

        $payment->refresh();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame('987654321012', $payment->reference_number);
        $this->assertSame('123456', $payment->trace_number);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'Advice') && $r->isForm() && $r['digitalreceipt'] === 'DR-ABC' && $r['Tid'] === '98765432');
    }

    public function test_irt_payment_is_sent_in_rials(): void
    {
        Http::fake([
            'sepehr.shaparak.ir:8081/V1/PeymentApi/GetToken' => Http::response(['Status' => 0, 'AccessToken' => 'tok']),
            '*.example.com/*' => Http::response('', 200),
        ]);

        $this->createPayment(['currency' => 'IRT', 'amount' => 50000]);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'GetToken') && (int) $r['Amount'] === 500000);
    }

    public function test_unsuccessful_respcode_fails_without_advice(): void
    {
        Http::fake([
            'sepehr.shaparak.ir:8081/V1/PeymentApi/GetToken' => Http::response(['Status' => 0, 'AccessToken' => 'tok']),
            '*.example.com/*' => Http::response('', 200),
        ]);
        $payment = $this->createPayment();

        $this->post("/api/v1/gateways/sepehr/callback/{$payment->public_id}", $this->callbackData($payment, ['respcode' => '-1', 'digitalreceipt' => '']));

        $this->assertStatus($payment, PaymentStatus::Failed);
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), 'Advice'));
    }

    public function test_amount_mismatch_in_advice_is_not_paid(): void
    {
        Http::fake([
            'sepehr.shaparak.ir:8081/V1/PeymentApi/GetToken' => Http::response(['Status' => 0, 'AccessToken' => 'tok']),
            'sepehr.shaparak.ir:8081/V1/PeymentApi/Advice' => Http::response(['Status' => 'Ok', 'ReturnId' => '1000']),
            '*.example.com/*' => Http::response('', 200),
        ]);
        $payment = $this->createPayment();

        $this->post("/api/v1/gateways/sepehr/callback/{$payment->public_id}", $this->callbackData($payment));

        $this->assertStatus($payment, PaymentStatus::Failed);
        $this->assertSame('AMOUNT_MISMATCH', $payment->latestAttempt->fresh()->error_code);
    }

    public function test_callback_for_other_invoice_is_rejected(): void
    {
        Http::fake([
            'sepehr.shaparak.ir:8081/V1/PeymentApi/GetToken' => Http::response(['Status' => 0, 'AccessToken' => 'tok']),
            '*.example.com/*' => Http::response('', 200),
        ]);
        $payment = $this->createPayment();

        $this->post("/api/v1/gateways/sepehr/callback/{$payment->public_id}", $this->callbackData($payment, ['invoiceid' => '1']));

        $this->assertStatus($payment, PaymentStatus::Pending);
    }

    public function test_ip_not_registered_error_is_explained(): void
    {
        Http::fake([
            'sepehr.shaparak.ir:8081/V1/PeymentApi/GetToken' => Http::response(['Status' => -2, 'Accesstoken' => null]),
            '*.example.com/*' => Http::response('', 200),
        ]);

        $payment = $this->createPayment();

        $this->assertStatus($payment, PaymentStatus::Failed);
        $this->assertSame('SEPEHR_-2', $payment->latestAttempt->error_code);
        $this->assertStringContainsString('port 8081', $payment->latestAttempt->error_message);
    }
}
