<?php

namespace Tests\Feature;

use App\Enums\WebhookStatus;
use App\Jobs\DeliverWebhook;
use App\Models\Payment;
use App\Models\WebhookDelivery;
use App\Payments\PaymentEventRecorder;
use App\Webhooks\WebhookService;
use App\Webhooks\WebhookUrlGuard;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookDeliveryTest extends TestCase
{
    private function paymentWithQueuedWebhook(): WebhookDelivery
    {
        Queue::fake();
        $auth = $this->makeClient();
        $this->makeMerchant($auth['client']);
        $this->signed($auth, 'POST', '/api/v1/payments', $this->paymentBody())->assertCreated();

        return WebhookDelivery::where('event', 'payment.created')->firstOrFail();
    }

    public function test_failed_delivery_is_retried_with_backoff(): void
    {
        $delivery = $this->paymentWithQueuedWebhook();
        Http::fake(['*' => Http::response('boom', 500)]);

        (new DeliverWebhook($delivery->id))->handle(app(PaymentEventRecorder::class));

        $delivery->refresh();
        $this->assertSame(WebhookStatus::Pending, $delivery->status);
        $this->assertSame(1, $delivery->attempt);
        $this->assertSame(500, $delivery->http_status);
        $this->assertEqualsWithDelta(now()->addSeconds(30)->timestamp, $delivery->next_retry_at->timestamp, 2);
        Queue::assertPushed(DeliverWebhook::class);
    }

    public function test_backoff_is_exponential_and_capped(): void
    {
        $this->assertSame(30, WebhookService::backoffSeconds(1));
        $this->assertSame(60, WebhookService::backoffSeconds(2));
        $this->assertSame(240, WebhookService::backoffSeconds(4));
        $this->assertSame(6 * 3600, WebhookService::backoffSeconds(30));
    }

    public function test_delivery_gives_up_after_max_attempts(): void
    {
        config(['payments.webhooks.max_attempts' => 2]);
        $delivery = $this->paymentWithQueuedWebhook();
        Http::fake(['*' => Http::response('', 503)]);
        $job = fn () => (new DeliverWebhook($delivery->id))->handle(app(PaymentEventRecorder::class));

        $job();
        $this->travel(31)->seconds();
        $job();

        $delivery->refresh();
        $this->assertSame(WebhookStatus::Failed, $delivery->status);
        $this->assertSame(2, $delivery->attempt);
        $this->assertNull($delivery->next_retry_at);
    }

    public function test_not_due_or_delivered_webhooks_are_not_resent(): void
    {
        $delivery = $this->paymentWithQueuedWebhook();
        Http::fake(['*' => Http::response('', 200)]);
        $job = fn () => (new DeliverWebhook($delivery->id))->handle(app(PaymentEventRecorder::class));

        $job();
        $job();
        $job();

        Http::assertSentCount(1);
        $this->assertSame(WebhookStatus::Delivered, $delivery->fresh()->status);
    }

    public function test_manual_retry_and_dispatch_due_command(): void
    {
        config(['payments.webhooks.max_attempts' => 1]);
        $delivery = $this->paymentWithQueuedWebhook();
        Http::fake(['*' => Http::sequence()->push('', 500)->push('', 200)]);

        (new DeliverWebhook($delivery->id))->handle(app(PaymentEventRecorder::class));
        $this->assertSame(WebhookStatus::Failed, $delivery->fresh()->status);

        app(WebhookService::class)->retry($delivery->fresh());
        $this->assertSame(WebhookStatus::Pending, $delivery->fresh()->status);

        Queue::fake();
        $this->artisan('webhooks:dispatch-due')->assertSuccessful();
        Queue::assertPushed(DeliverWebhook::class, fn ($job) => $job->deliveryId === $delivery->id);

        (new DeliverWebhook($delivery->id))->handle(app(PaymentEventRecorder::class));
        $this->assertSame(WebhookStatus::Delivered, $delivery->fresh()->status);
    }

    public function test_events_are_deduplicated(): void
    {
        $delivery = $this->paymentWithQueuedWebhook();
        $payment = Payment::find($delivery->payment_id);

        app(WebhookService::class)->enqueue($payment, 'payment.created');
        app(WebhookService::class)->enqueue($payment, 'payment.created');

        $this->assertSame(1, WebhookDelivery::where('event', 'payment.created')->count());
    }

    public function test_payment_state_is_final_even_if_webhook_fails(): void
    {
        Http::fake(['*' => Http::response('', 500)]);
        $auth = $this->makeClient();
        $this->makeMerchant($auth['client']);
        $id = $this->signed($auth, 'POST', '/api/v1/payments', $this->paymentBody())->assertCreated()->json('payment_id');
        $this->get("/pay/{$id}");
        $payment = Payment::where('public_id', $id)->first();
        $location = $this->post("/sandbox-psp/{$payment->token}", ['outcome' => 'success'])->headers->get('Location');
        $this->get($location);

        $this->assertSame('paid', $payment->fresh()->status->value);
        $this->assertSame(WebhookStatus::Pending, WebhookDelivery::where('event', 'payment.succeeded')->first()->status);
    }

    public function test_private_network_endpoints_are_refused(): void
    {
        config(['payments.webhooks.block_private_networks' => true]);
        $this->assertFalse(WebhookUrlGuard::isAllowed('https://127.0.0.1/hook'));
        $this->assertFalse(WebhookUrlGuard::isAllowed('https://10.0.0.5/hook'));
        $this->assertFalse(WebhookUrlGuard::isAllowed('http://8.8.8.8/hook'));
    }
}
