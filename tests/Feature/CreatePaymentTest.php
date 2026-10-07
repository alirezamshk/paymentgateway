<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Gateways\Data\GatewayCreateResult;
use App\Gateways\GatewayManager;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Payments\PaymentService;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeGateway;
use Tests\TestCase;

class CreatePaymentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => Http::response('', 200)]); // webhook endpoints
    }

    public function test_create_payment_returns_tech_kala_payment_url(): void
    {
        $auth = $this->makeClient();
        $merchant = $this->makeMerchant($auth['client']);

        $response = $this->signed($auth, 'POST', '/api/v1/payments', $this->paymentBody([
            'order_id' => 'ORD-10001',
            'return_url' => 'https://panel-a.example.com/payment/result',
        ]))->assertCreated();

        $paymentId = $response->json('payment_id');
        $this->assertMatchesRegularExpression('/^pay_[0-9a-z]{26}$/', $paymentId);
        $response->assertJsonPath('status', 'pending')
            ->assertJsonPath('order_id', 'ORD-10001')
            ->assertJsonPath('amount', 500000)
            ->assertJsonPath('currency', 'IRR')
            ->assertJsonPath('merchant_id', $merchant->public_id)
            ->assertJsonPath('payment_url', "https://pay.tech-kala.test/pay/{$paymentId}")
            ->assertJsonMissingPath('id')
            ->assertJsonMissingPath('client_id');

        $payment = Payment::where('public_id', $paymentId)->first();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertNull($payment->paid_at);
        $this->assertCount(1, $payment->attempts);
        $this->assertEqualsCanonicalizing(
            ['payment.created', 'gateway.requested', 'gateway.response_received', 'payment.pending', 'webhook.queued', 'webhook.queued', 'webhook.delivered', 'webhook.delivered'],
            PaymentEvent::where('payment_id', $payment->id)->pluck('event')->all(),
        );
    }

    public function test_amount_must_be_an_integer_within_limits(): void
    {
        $auth = $this->makeClient();
        $this->makeMerchant($auth['client']);

        foreach ([1000.5, '500000', 5, -100, 0] as $amount) {
            $this->signed($auth, 'POST', '/api/v1/payments', $this->paymentBody(['amount' => $amount]))
                ->assertStatus(422)
                ->assertJsonPath('error.code', 'VALIDATION_ERROR');
        }

        $this->signed($auth, 'POST', '/api/v1/payments', $this->paymentBody(['currency' => 'USD']))->assertStatus(422);
        $this->signed($auth, 'POST', '/api/v1/payments', $this->paymentBody(['return_url' => 'http://insecure.example.com']))->assertStatus(422);
        $this->signed($auth, 'POST', '/api/v1/payments', $this->paymentBody(['currency' => 'IRT', 'amount' => 50000]))->assertCreated();
    }

    public function test_explicit_merchant_must_belong_to_client(): void
    {
        $a = $this->makeClient('site-a');
        $b = $this->makeClient('site-b');
        $this->makeMerchant($a['client']);
        $merchantB = $this->makeMerchant($b['client']);

        $this->signed($a, 'POST', '/api/v1/payments', $this->paymentBody(['merchant_id' => $merchantB->public_id]))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'MERCHANT_NOT_FOUND');

        $this->assertSame(0, Payment::count());
    }

    public function test_explicit_merchant_selection(): void
    {
        $auth = $this->makeClient();
        $this->makeMerchant($auth['client']);
        $second = $this->makeMerchant($auth['client'], default: false);

        $this->signed($auth, 'POST', '/api/v1/payments', $this->paymentBody(['merchant_id' => $second->public_id]))
            ->assertCreated()
            ->assertJsonPath('merchant_id', $second->public_id);
    }

    public function test_disabled_merchant_or_provider_cannot_be_used(): void
    {
        $auth = $this->makeClient();
        $merchant = $this->makeMerchant($auth['client']);

        $merchant->update(['status' => 'disabled']);
        $this->signed($auth, 'POST', '/api/v1/payments', $this->paymentBody())->assertStatus(422)->assertJsonPath('error.code', 'MERCHANT_NOT_FOUND');

        $merchant->update(['status' => 'active']);
        $merchant->provider->update(['status' => 'disabled']);
        $this->signed($auth, 'POST', '/api/v1/payments', $this->paymentBody())->assertStatus(422)->assertJsonPath('error.code', 'PROVIDER_UNAVAILABLE');
    }

    public function test_no_merchant_configured(): void
    {
        $auth = $this->makeClient();

        $this->signed($auth, 'POST', '/api/v1/payments', $this->paymentBody())
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'MERCHANT_NOT_FOUND');
    }

    public function test_idempotency_key_replay_returns_same_payment(): void
    {
        $auth = $this->makeClient();
        $this->makeMerchant($auth['client']);
        $body = $this->paymentBody();

        $first = $this->signed($auth, 'POST', '/api/v1/payments', $body, ['Idempotency-Key' => 'idem-key-0001'])->assertCreated();
        $second = $this->signed($auth, 'POST', '/api/v1/payments', $body, ['Idempotency-Key' => 'idem-key-0001'])->assertOk();

        $this->assertSame($first->json('payment_id'), $second->json('payment_id'));
        $this->assertSame(1, Payment::count());

        $this->signed($auth, 'POST', '/api/v1/payments', $this->paymentBody(), ['Idempotency-Key' => 'idem-key-0001'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REUSED');
    }

    public function test_duplicate_order_returns_existing_or_conflicts(): void
    {
        $auth = $this->makeClient();
        $this->makeMerchant($auth['client']);
        $body = $this->paymentBody(['order_id' => 'ORD-1']);

        $first = $this->signed($auth, 'POST', '/api/v1/payments', $body)->assertCreated();
        $this->signed($auth, 'POST', '/api/v1/payments', $body)->assertOk()->assertJsonPath('payment_id', $first->json('payment_id'));

        $this->signed($auth, 'POST', '/api/v1/payments', array_merge($body, ['amount' => 600000]))
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'ORDER_ALREADY_EXISTS');

        $this->assertSame(1, Payment::count());
    }

    public function test_same_order_id_is_independent_per_client(): void
    {
        $a = $this->makeClient('site-a');
        $b = $this->makeClient('site-b');
        $this->makeMerchant($a['client']);
        $this->makeMerchant($b['client']);

        $this->signed($a, 'POST', '/api/v1/payments', $this->paymentBody(['order_id' => 'ORD-1']))->assertCreated();
        $this->signed($b, 'POST', '/api/v1/payments', $this->paymentBody(['order_id' => 'ORD-1']))->assertCreated();
    }

    public function test_gateway_failure_marks_payment_failed_and_new_attempt_is_explicit(): void
    {
        $fake = new FakeGateway;
        $fake->onCreate = fn () => GatewayCreateResult::failure('PSP_DOWN', 'PSP rejected the request');
        app(GatewayManager::class)->extend('sandbox', $fake);

        $auth = $this->makeClient();
        $this->makeMerchant($auth['client']);
        $body = $this->paymentBody(['order_id' => 'ORD-RETRY']);

        $this->signed($auth, 'POST', '/api/v1/payments', $body)->assertCreated()->assertJsonPath('status', 'failed');

        // Plain repeat does not start a new attempt.
        $this->signed($auth, 'POST', '/api/v1/payments', $body)->assertOk()->assertJsonPath('status', 'failed');
        $this->assertSame(1, $fake->createCalls);

        $fake->onCreate = null;
        $this->signed($auth, 'POST', '/api/v1/payments', $body + ['new_attempt' => true])
            ->assertCreated()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('attempts', 2);

        // A new attempt is refused while the payment is not failed.
        $this->signed($auth, 'POST', '/api/v1/payments', $body + ['new_attempt' => true])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'NEW_ATTEMPT_NOT_ALLOWED');
    }

    public function test_cancel(): void
    {
        $auth = $this->makeClient();
        $this->makeMerchant($auth['client']);
        $id = $this->signed($auth, 'POST', '/api/v1/payments', $this->paymentBody())->json('payment_id');

        $this->signed($auth, 'POST', "/api/v1/payments/{$id}/cancel")->assertOk()->assertJsonPath('status', 'cancelled');
        $this->signed($auth, 'POST', "/api/v1/payments/{$id}/cancel")->assertStatus(409)->assertJsonPath('error.code', 'PAYMENT_NOT_CANCELLABLE');
    }

    public function test_internal_errors_do_not_leak_details(): void
    {
        $fake = new FakeGateway;
        app(GatewayManager::class)->extend('sandbox', $fake);
        $auth = $this->makeClient();
        $this->makeMerchant($auth['client']);

        $this->mock(PaymentService::class, fn ($m) => $m->shouldReceive('create')->andThrow(new \RuntimeException('SQLSTATE[HY000] secret table info')));

        $this->signed($auth, 'POST', '/api/v1/payments', $this->paymentBody())
            ->assertStatus(500)
            ->assertJsonPath('error.code', 'INTERNAL_ERROR')
            ->assertDontSee('SQLSTATE');
    }
}
