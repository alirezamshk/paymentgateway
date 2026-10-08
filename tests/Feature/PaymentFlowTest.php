<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\WebhookDelivery;
use App\Webhooks\WebhookSigner;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Full flow through the internal sandbox PSP:
 * create -> payment page -> PSP -> callback -> verify -> paid -> webhook -> return_url.
 */
class PaymentFlowTest extends TestCase
{
    public function test_end_to_end_successful_payment(): void
    {
        Http::fake(['https://site-a.example.com/*' => Http::response(['ok' => true], 200)]);
        $auth = $this->makeClient();
        $this->makeMerchant($auth['client']);

        $id = $this->signed($auth, 'POST', '/api/v1/payments', $this->paymentBody(['order_id' => 'ORD-10001']))->json('payment_id');

        // Payment page shows site name / amount and forwards to PSP.
        $page = $this->get("/pay/{$id}")->assertOk()->assertSee('Site-A')->assertSee('500,000');
        $this->assertStringContainsString("script-src 'nonce-", $page->headers->get('Content-Security-Policy'));
        $this->assertSame(PaymentStatus::Redirected, Payment::where('public_id', $id)->first()->status);

        $token = Payment::where('public_id', $id)->first()->token;
        $this->get("/sandbox-psp/{$token}")->assertOk();
        $callback = $this->post("/sandbox-psp/{$token}", ['outcome' => 'success'])->assertRedirect();

        // Follow the PSP redirect to our callback.
        $location = $callback->headers->get('Location');
        $this->assertStringStartsWith("https://pay.example.test/api/v1/gateways/sandbox/callback/{$id}", $location);
        $final = $this->get($location)->assertStatus(303);

        $this->assertSame(
            "https://site-a.example.com/payment/result?payment_id={$id}&order_id=ORD-10001&status=paid",
            $final->headers->get('Location'),
        );

        $payment = Payment::where('public_id', $id)->first();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertNotNull($payment->reference_number);
        $this->assertSame('603799******0000', $payment->card_mask);

        // API reflects final state.
        $this->signed($auth, 'GET', "/api/v1/payments/{$id}")->assertOk()->assertJsonPath('status', 'paid')->assertJsonPath('payment_url', null);

        // Webhook was signed and delivered.
        $delivery = WebhookDelivery::where('event', 'payment.succeeded')->first();
        $this->assertSame('delivered', $delivery->status->value);

        Http::assertSent(function (Request $request) use ($auth, $id) {
            if (($request->header('X-Webhook-Event')[0] ?? null) !== 'payment.succeeded') {
                return false;
            }
            $body = json_decode($request->body(), true);

            return $body['event'] === 'payment.succeeded'
                && $body['payment_id'] === $id
                && $body['status'] === 'paid'
                && WebhookSigner::verify($auth['webhook_secret'], (int) $request->header('X-Webhook-Timestamp')[0], $request->body(), $request->header('X-Webhook-Signature')[0])
                && ! str_contains($request->body(), $auth['secret']);
        });

        // Timeline is complete.
        $events = $payment->events()->pluck('event')->all();
        foreach (['payment.created', 'gateway.requested', 'gateway.response_received', 'payment.pending', 'payment.redirected',
            'callback.received', 'payment.callback_received', 'payment.verifying', 'verification.requested',
            'verification.success', 'payment.paid', 'webhook.queued', 'webhook.delivered'] as $expected) {
            $this->assertContains($expected, $events);
        }
    }

    public function test_failed_payment_at_psp(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        $auth = $this->makeClient();
        $this->makeMerchant($auth['client']);

        $id = $this->signed($auth, 'POST', '/api/v1/payments', $this->paymentBody())->json('payment_id');
        $this->get("/pay/{$id}");
        $token = Payment::where('public_id', $id)->first()->token;
        $location = $this->post("/sandbox-psp/{$token}", ['outcome' => 'fail'])->headers->get('Location');

        $this->get($location)->assertRedirectContains('status=failed');
        $this->assertSame(PaymentStatus::Failed, Payment::where('public_id', $id)->first()->status);
        $this->assertDatabaseHas('webhook_deliveries', ['event' => 'payment.failed']);
    }

    public function test_payment_page_does_not_expose_secrets_or_internal_ids(): void
    {
        $auth = $this->makeClient();
        $merchant = $this->makeMerchant($auth['client']);
        $merchant->forceFill(['encrypted_password' => 'super-secret-psp-password', 'username' => 'psp-user'])->save();
        Http::fake(['*' => Http::response('', 200)]);

        $id = $this->signed($auth, 'POST', '/api/v1/payments', $this->paymentBody())->json('payment_id');
        $html = $this->get("/pay/{$id}")->getContent();

        $this->assertStringNotContainsString('super-secret-psp-password', $html);
        $this->assertStringNotContainsString('psp-user', $html);
        $this->assertStringNotContainsString($auth['secret'], $html);
        $this->assertStringNotContainsString($auth['webhook_secret'], $html);
        $this->assertStringNotContainsString($merchant->public_id, $html);
    }

    public function test_unknown_payment_page_is_404(): void
    {
        $this->get('/pay/pay_01jxxxxxxxxxxxxxxxxxxxxxxx')->assertNotFound();
    }

    public function test_expired_payment_cannot_be_paid(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        $auth = $this->makeClient();
        $this->makeMerchant($auth['client']);
        $id = $this->signed($auth, 'POST', '/api/v1/payments', $this->paymentBody())->json('payment_id');

        $this->travel(61)->minutes();
        $this->artisan('payments:expire')->assertSuccessful();

        $this->assertSame(PaymentStatus::Expired, Payment::where('public_id', $id)->first()->status);
        $this->assertDatabaseHas('webhook_deliveries', ['event' => 'payment.expired']);
        $this->get("/pay/{$id}")->assertOk()->assertDontSee('psp-form');
    }
}
