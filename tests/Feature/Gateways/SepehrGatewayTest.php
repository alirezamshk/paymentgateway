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
            'sepehr.shaparak.ir/Rest/V1/PeymentApi/GetToken' => Http::response(['Status' => 0, 'Accesstoken' => 'tok-123']),
            'sepehr.shaparak.ir/Rest/V1/PeymentApi/Advice' => Http::response(['Status' => 'Ok', 'ReturnId' => '500000', 'Message' => 'ok']),
            '*.example.com/*' => Http::response('', 200),
        ]);

        $payment = $this->createPayment();
        $redirect = $payment->latestAttempt->redirect_payload;
        $this->assertSame('GET', $redirect['method']);
        $this->assertSame('https://sepehr.shaparak.ir/Payment/Pay', $redirect['url']);
        $this->assertSame(['token' => 'tok-123', 'terminalid' => '98765432'], $redirect['fields']);

        // The payment page forwards token + terminalid to Sepehr.
        $this->get("/pay/{$payment->public_id}")
            ->assertSee('action="https://sepehr.shaparak.ir/Payment/Pay"', false)
            ->assertSee('name="token" value="tok-123"', false)
            ->assertSee('name="terminalid" value="98765432"', false);
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
            'sepehr.shaparak.ir/Rest/V1/PeymentApi/GetToken' => Http::response(['Status' => 0, 'AccessToken' => 'tok']),
            '*.example.com/*' => Http::response('', 200),
        ]);

        $this->createPayment(['currency' => 'IRT', 'amount' => 50000]);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'GetToken') && (int) $r['Amount'] === 500000);
    }

    public function test_unsuccessful_respcode_fails_without_advice(): void
    {
        Http::fake([
            'sepehr.shaparak.ir/Rest/V1/PeymentApi/GetToken' => Http::response(['Status' => 0, 'AccessToken' => 'tok']),
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
            'sepehr.shaparak.ir/Rest/V1/PeymentApi/GetToken' => Http::response(['Status' => 0, 'AccessToken' => 'tok']),
            'sepehr.shaparak.ir/Rest/V1/PeymentApi/Advice' => Http::response(['Status' => 'Ok', 'ReturnId' => '1000']),
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
            'sepehr.shaparak.ir/Rest/V1/PeymentApi/GetToken' => Http::response(['Status' => 0, 'AccessToken' => 'tok']),
            '*.example.com/*' => Http::response('', 200),
        ]);
        $payment = $this->createPayment();

        $this->post("/api/v1/gateways/sepehr/callback/{$payment->public_id}", $this->callbackData($payment, ['invoiceid' => '1']));

        $this->assertStatus($payment, PaymentStatus::Pending);
    }

    public function test_ip_not_registered_error_is_explained(): void
    {
        Http::fake([
            'sepehr.shaparak.ir/Rest/V1/PeymentApi/GetToken' => Http::response(['Status' => -2, 'Accesstoken' => null]),
            '*.example.com/*' => Http::response('', 200),
        ]);

        $payment = $this->createPayment();

        $this->assertStatus($payment, PaymentStatus::Failed);
        $this->assertSame('SEPEHR_-2', $payment->latestAttempt->error_code);
        $this->assertStringContainsString('port 8081', $payment->latestAttempt->error_message);
    }

    public function test_receipt_of_one_payment_cannot_pay_another(): void
    {
        Http::fake([
            'sepehr.shaparak.ir/Rest/V1/PeymentApi/GetToken' => Http::response(['Status' => 0, 'Accesstoken' => 'tok-123']),
            // Sepehr answers Duplicate (with the original amount) for an already advised receipt.
            'sepehr.shaparak.ir/Rest/V1/PeymentApi/Advice' => Http::sequence()
                ->push(['Status' => 'Ok', 'ReturnId' => '500000'])
                ->whenEmpty(Http::response(['Status' => 'Duplicate', 'ReturnId' => '500000'])),
            '*.example.com/*' => Http::response('', 200),
        ]);

        $a = $this->createPayment();
        $this->post("/api/v1/gateways/sepehr/callback/{$a->public_id}", $this->callbackData($a));
        $this->assertSame(null, $a->latestAttempt->fresh()->error_code);
        $this->assertStatus($a, PaymentStatus::Paid);

        // Same receipt replayed onto another payment of the same amount.
        $b = $this->createPayment();
        $this->post("/api/v1/gateways/sepehr/callback/{$b->public_id}", $this->callbackData($b));
        $this->assertStatus($b, PaymentStatus::Failed);
        $this->assertSame('RECEIPT_REUSED', $b->latestAttempt->fresh()->error_code);
    }

    public function test_duplicate_is_only_accepted_on_a_retry_of_the_same_attempt(): void
    {
        Http::fake([
            'sepehr.shaparak.ir/Rest/V1/PeymentApi/GetToken' => Http::response(['Status' => 0, 'Accesstoken' => 'tok-123']),
            'sepehr.shaparak.ir/Rest/V1/PeymentApi/Advice' => Http::sequence()
                ->push('', 503)                                            // timeout-like failure after Sepehr processed it
                ->push(['Status' => 'Duplicate', 'ReturnId' => '500000']), // our retry
            '*.example.com/*' => Http::response('', 200),
        ]);

        $payment = $this->createPayment();
        $this->post("/api/v1/gateways/sepehr/callback/{$payment->public_id}", $this->callbackData($payment));
        $this->assertStatus($payment, PaymentStatus::CallbackReceived);

        $this->artisan('payments:reconcile')->assertSuccessful();
        $this->assertStatus($payment, PaymentStatus::Paid);
    }

    public function test_receipt_advised_elsewhere_is_rejected(): void
    {
        // E.g. the receipt was already confirmed by another system sharing the terminal.
        Http::fake([
            'sepehr.shaparak.ir/Rest/V1/PeymentApi/GetToken' => Http::response(['Status' => 0, 'Accesstoken' => 'tok-123']),
            'sepehr.shaparak.ir/Rest/V1/PeymentApi/Advice' => Http::response(['Status' => 'Duplicate', 'ReturnId' => '500000']),
            '*.example.com/*' => Http::response('', 200),
        ]);

        $payment = $this->createPayment();
        $this->post("/api/v1/gateways/sepehr/callback/{$payment->public_id}", $this->callbackData($payment, ['digitalreceipt' => 'DR-FOREIGN']));
        $this->assertStatus($payment, PaymentStatus::Failed);
        $this->assertSame('RECEIPT_REUSED', $payment->latestAttempt->fresh()->error_code);
    }
}
