<?php

namespace Tests\Feature;

use App\Gateways\Data\GatewayVerifyResult;
use App\Gateways\GatewayManager;
use App\Models\Payment;
use App\Webhooks\WebhookSigner;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeGateway;
use Tests\TestCase;

/**
 * Follows docs/BILLING_PANEL_BRIEF.md step by step, acting as the billing panel, to prove the
 * documented round trip (panel → Tech-Kala → bank → Tech-Kala → panel) matches the service.
 */
class BillingPanelRoundTripTest extends TestCase
{
    private const PANEL = 'https://panel.v2moon.shop';

    private FakeGateway $bank;

    private array $auth;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bank = new FakeGateway;
        app(GatewayManager::class)->extend('sandbox', $this->bank);

        $this->auth = $this->makeClient('v2moon', [
            'webhook_url' => self::PANEL.'/api/payment-gateway/techkala/webhook',
            'return_url' => self::PANEL.'/',
        ]);
        $this->makeMerchant($this->auth['client']);
        Http::fake([self::PANEL.'/*' => Http::response(['ok' => true], 200)]);
    }

    /** Brief 3a/3b: create the payment for invoice 123 (panel stores Tomans). */
    private function payOnline(string $orderId = 'INV-123-1', int $tomans = 50000, array $extra = [], ?string $key = null): array
    {
        return $this->signed($this->auth, 'POST', '/api/v1/payments', array_merge([
            'order_id' => $orderId,
            'amount' => $tomans,
            'currency' => 'IRT',
            'description' => 'Invoice #123 - V2Moon',
            'return_url' => self::PANEL.'/payments/techkala/return?invoice=123',
        ], $extra), ['Idempotency-Key' => $key ?? 'tk-'.$orderId])->json();
    }

    public function test_full_round_trip_through_tech_kala(): void
    {
        // Panel → Tech-Kala: create payment, get payment_url on Tech-Kala's domain.
        $p = $this->payOnline();
        $this->assertSame('pending', $p['status']);
        $this->assertSame(50000, $p['amount']);
        $this->assertSame('IRT', $p['currency']);
        $this->assertStringStartsWith('https://pay.example.test/pay/pay_', $p['payment_url']);

        // Double click (same Idempotency-Key) does not create a second payment.
        $this->assertSame($p['payment_id'], $this->payOnline()['payment_id']);
        $this->assertSame(1, Payment::count());

        // Customer → Tech-Kala payment page → bank (form to the PSP).
        $this->get(parse_url($p['payment_url'], PHP_URL_PATH))->assertOk()->assertSee('psp-form');

        // Bank → Tech-Kala callback → verify → redirect back to the PANEL with the documented params.
        $payment = Payment::where('public_id', $p['payment_id'])->first();
        $back = $this->post("/api/v1/gateways/sandbox/callback/{$payment->public_id}", ['authority' => $payment->latestAttempt->authority]);
        $back->assertStatus(303);
        $this->assertSame(
            self::PANEL.'/payments/techkala/return?invoice=123&payment_id='.$p['payment_id'].'&order_id=INV-123-1&status=paid',
            $back->headers->get('Location'),
        );

        // Return endpoint confirms with GET /payments/{id} (brief section 4).
        $this->signed($this->auth, 'GET', '/api/v1/payments/'.$p['payment_id'])
            ->assertOk()->assertJsonPath('status', 'paid')->assertJsonPath('amount', 50000)->assertJsonPath('currency', 'IRT');

        // Tech-Kala → panel webhook, signed exactly as section 5 describes.
        Http::assertSent(function (Request $r) use ($p) {
            if ($r->url() !== self::PANEL.'/api/payment-gateway/techkala/webhook' || ($r->header('X-Webhook-Event')[0] ?? '') !== 'payment.succeeded') {
                return false;
            }
            $body = json_decode($r->body(), true);

            return $body['payment_id'] === $p['payment_id']
                && $body['order_id'] === 'INV-123-1'
                && $body['amount'] === 50000 && $body['currency'] === 'IRT' && $body['status'] === 'paid'
                && $r->hasHeader('X-Webhook-Delivery-Id')
                && WebhookSigner::verify($this->auth['webhook_secret'], (int) $r->header('X-Webhook-Timestamp')[0], $r->body(), $r->header('X-Webhook-Signature')[0]);
        });
    }

    public function test_failed_payment_retry_needs_a_new_idempotency_key(): void
    {
        $this->bank->onVerify = fn () => GatewayVerifyResult::rejected('DECLINED', 'Card declined');
        $p = $this->payOnline();
        $payment = Payment::where('public_id', $p['payment_id'])->first();
        $this->post("/api/v1/gateways/sandbox/callback/{$payment->public_id}", ['authority' => $payment->latestAttempt->authority])
            ->assertRedirectContains('status=failed');

        // Same key → original failed payment, no new attempt (documented trap).
        $this->assertSame(1, $this->payOnline(extra: ['new_attempt' => true])['attempts']);

        // New key → new bank attempt on the same payment_id (brief 3b).
        $retry = $this->payOnline(extra: ['new_attempt' => true], key: 'tk-INV-123-1-retry-1');
        $this->assertSame($p['payment_id'], $retry['payment_id']);
        $this->assertSame(2, $retry['attempts']);
        $this->assertSame('pending', $retry['status']);
    }

    public function test_amount_change_cancels_old_payment_and_uses_new_order_id(): void
    {
        $old = $this->payOnline();

        $this->signed($this->auth, 'POST', '/api/v1/payments/'.$old['payment_id'].'/cancel')->assertOk()->assertJsonPath('status', 'cancelled');
        $new = $this->payOnline('INV-123-2', 60000);

        $this->assertNotSame($old['payment_id'], $new['payment_id']);
        $this->assertSame('pending', $new['status']);
        // The cancelled payment can no longer be paid.
        $this->get('/pay/'.$old['payment_id'])->assertOk()->assertDontSee('psp-form');
    }

    public function test_reusing_order_id_with_new_amount_is_rejected(): void
    {
        $this->payOnline();

        $this->signed($this->auth, 'POST', '/api/v1/payments', [
            'order_id' => 'INV-123-1', 'amount' => 60000, 'currency' => 'IRT',
        ])->assertStatus(409)->assertJsonPath('error.code', 'ORDER_ALREADY_EXISTS');
    }
}
