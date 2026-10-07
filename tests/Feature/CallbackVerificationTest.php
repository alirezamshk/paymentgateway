<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Exceptions\GatewayException;
use App\Gateways\Data\GatewayVerifyResult;
use App\Gateways\GatewayManager;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Payments\VerificationService;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeGateway;
use Tests\TestCase;

class CallbackVerificationTest extends TestCase
{
    private FakeGateway $fake;

    private array $auth;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => Http::response('', 200)]);
        $this->fake = new FakeGateway;
        app(GatewayManager::class)->extend('sandbox', $this->fake);
        $this->auth = $this->makeClient();
        $this->makeMerchant($this->auth['client']);
    }

    private function createPayment(): Payment
    {
        $id = $this->signed($this->auth, 'POST', '/api/v1/payments', $this->paymentBody())->json('payment_id');
        $this->get("/pay/{$id}");

        return Payment::where('public_id', $id)->first();
    }

    private function sendCallback(Payment $payment, array $data = [], string $provider = 'sandbox')
    {
        $data += ['authority' => $payment->latestAttempt->authority, 'Status' => 'OK'];

        return $this->post("/api/v1/gateways/{$provider}/callback/{$payment->public_id}", $data);
    }

    public function test_success_requires_psp_verification(): void
    {
        $payment = $this->createPayment();
        $this->fake->onVerify = fn () => GatewayVerifyResult::rejected('NOT_PAID', 'PSP says not paid');

        // Callback claims success, PSP verification says otherwise.
        $this->sendCallback($payment, ['Status' => 'OK'])->assertRedirectContains('status=failed');

        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
        $this->assertSame(1, $this->fake->verifyCalls);
    }

    public function test_callback_with_wrong_reference_is_rejected(): void
    {
        $payment = $this->createPayment();

        $this->sendCallback($payment, ['authority' => 'forged'])->assertRedirectContains('status=redirected');

        $this->assertSame(PaymentStatus::Redirected, $payment->fresh()->status);
        $this->assertSame(0, $this->fake->verifyCalls);
        $this->assertDatabaseHas('payment_events', ['payment_id' => $payment->id, 'event' => 'callback.rejected']);
    }

    public function test_callback_on_wrong_provider_route_is_rejected(): void
    {
        $payment = $this->createPayment();

        $this->sendCallback($payment, [], 'zarinpal');

        $this->assertSame(PaymentStatus::Redirected, $payment->fresh()->status);
        $this->assertSame(0, $this->fake->verifyCalls);
    }

    public function test_duplicate_callbacks_verify_once(): void
    {
        $payment = $this->createPayment();

        $this->sendCallback($payment)->assertRedirectContains('status=paid');
        $this->sendCallback($payment)->assertRedirectContains('status=paid');
        $this->sendCallback($payment)->assertRedirectContains('status=paid');

        $this->assertSame(1, $this->fake->verifyCalls);
        $this->assertSame(1, PaymentEvent::where('payment_id', $payment->id)->where('event', 'payment.paid')->count());
        $this->assertDatabaseCount('webhook_deliveries', 3); // created, pending, succeeded
    }

    public function test_concurrent_callback_during_verification_is_ignored(): void
    {
        $payment = $this->createPayment();
        $nestedStatus = null;

        // While the first verification is talking to the PSP, a second callback arrives.
        $this->fake->onVerify = function (Payment $p, $attempt) use (&$nestedStatus, $payment) {
            if ($this->fake->verifyCalls === 1) {
                $nested = app(VerificationService::class)->handleCallback('sandbox', Payment::find($payment->id), ['authority' => $attempt->authority]);
                $nestedStatus = $nested->status;
            }

            return GatewayVerifyResult::verified('REF-1');
        };

        $this->sendCallback($payment)->assertRedirectContains('status=paid');

        $this->assertSame(PaymentStatus::Verifying, $nestedStatus);
        $this->assertSame(1, $this->fake->verifyCalls);
        $this->assertSame(1, PaymentEvent::where('payment_id', $payment->id)->where('event', 'payment.paid')->count());
    }

    public function test_paid_payment_never_changes_again(): void
    {
        $payment = $this->createPayment();
        $this->sendCallback($payment);
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);

        $this->fake->onVerify = fn () => GatewayVerifyResult::rejected('X', 'Y');
        $this->sendCallback($payment);
        $this->signed($this->auth, 'POST', "/api/v1/payments/{$payment->public_id}/verify")->assertJsonPath('status', 'paid');
        $this->signed($this->auth, 'POST', "/api/v1/payments/{$payment->public_id}/cancel")->assertStatus(409);
        $this->artisan('payments:expire');

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
    }

    public function test_transient_verify_error_is_retried_via_verify_api(): void
    {
        $payment = $this->createPayment();
        $this->fake->onVerify = fn () => throw new GatewayException('timeout');

        $this->sendCallback($payment)->assertRedirectContains('status=callback_received');
        $this->assertSame(PaymentStatus::CallbackReceived, $payment->fresh()->status);
        $this->assertDatabaseHas('payment_events', ['payment_id' => $payment->id, 'event' => 'verification.error']);

        $this->fake->onVerify = null;
        $this->signed($this->auth, 'POST', "/api/v1/payments/{$payment->public_id}/verify")
            ->assertOk()
            ->assertJsonPath('status', 'paid');
    }

    public function test_reconcile_command_recovers_stale_verification(): void
    {
        $payment = $this->createPayment();
        $this->fake->onVerify = fn () => throw new GatewayException('timeout');
        $this->sendCallback($payment);

        $this->fake->onVerify = null;
        $this->artisan('payments:reconcile')->assertSuccessful();

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
    }

    public function test_verify_api_does_nothing_before_callback(): void
    {
        $payment = $this->createPayment();

        $this->signed($this->auth, 'POST', "/api/v1/payments/{$payment->public_id}/verify")->assertOk()->assertJsonPath('status', 'redirected');
        $this->assertSame(0, $this->fake->verifyCalls);
    }

    public function test_raw_callback_is_recorded_masked(): void
    {
        $payment = $this->createPayment();
        $this->sendCallback($payment, ['cardnumber' => '6037990000001234', 'password' => 'should-not-be-stored']);

        $event = PaymentEvent::where('payment_id', $payment->id)->where('event', 'callback.received')->first();
        $this->assertSame('603799******1234', $event->metadata['fields']['cardnumber']);
        $this->assertSame('[REDACTED]', $event->metadata['fields']['password']);
    }

    public function test_unknown_payment_callback(): void
    {
        $this->post('/api/v1/gateways/sandbox/callback/pay_doesnotexist')->assertNotFound();
    }
}
