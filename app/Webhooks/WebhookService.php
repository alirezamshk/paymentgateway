<?php

namespace App\Webhooks;

use App\Enums\WebhookStatus;
use App\Jobs\DeliverWebhook;
use App\Models\Payment;
use App\Models\WebhookDelivery;
use App\Payments\PaymentEventRecorder;
use App\Support\RequestContext;

/**
 * Creates webhook delivery records. Delivery itself happens on the queue, so a slow or
 * failing client endpoint can never block or undo a payment state change.
 */
class WebhookService
{
    public function __construct(private readonly PaymentEventRecorder $events) {}

    /** Queue an event for the payment's client. Idempotent (see dedupe key). */
    public function enqueue(Payment $payment, string $event): ?WebhookDelivery
    {
        $client = $payment->client;

        // Admin test payments never reach the client site.
        if ($client === null || empty($client->webhook_url) || $payment->is_test) {
            return null;
        }

        // pending/failed can legitimately repeat once per attempt; every other event happens
        // at most once per payment.
        $dedupeKey = in_array($event, ['payment.pending', 'payment.failed'], true)
            ? "{$payment->public_id}:{$event}:{$payment->attempts_count}"
            : "{$payment->public_id}:{$event}";

        $delivery = WebhookDelivery::firstOrCreate(
            ['dedupe_key' => $dedupeKey],
            [
                'client_id' => $client->id,
                'payment_id' => $payment->id,
                'event' => $event,
                'endpoint' => $client->webhook_url,
                'payload' => $this->payload($payment, $event),
                'attempt' => 0,
                'status' => WebhookStatus::Pending,
                'request_id' => RequestContext::idOrNew(),
                'next_retry_at' => now(),
            ],
        );

        if ($delivery->wasRecentlyCreated) {
            $this->events->record($payment, 'webhook.queued', 'system', [
                'event' => $event,
                'delivery_id' => $delivery->public_id,
            ]);

            DeliverWebhook::dispatch($delivery->id)->afterCommit();
        }

        return $delivery;
    }

    /** Re-queue a delivery manually (admin). Resets the attempt budget. */
    public function retry(WebhookDelivery $delivery): void
    {
        $delivery->update([
            'status' => WebhookStatus::Pending,
            'attempt' => 0,
            'endpoint' => $delivery->client->webhook_url ?: $delivery->endpoint,
            'next_retry_at' => now(),
            'last_error' => null,
        ]);

        DeliverWebhook::dispatch($delivery->id)->afterCommit();
    }

    /** @return array<string, mixed> */
    public function payload(Payment $payment, string $event): array
    {
        return [
            'event' => $event,
            'payment_id' => $payment->public_id,
            'order_id' => $payment->order_id,
            'amount' => $payment->amount,
            'currency' => $payment->currency->value,
            'status' => $payment->status->value,
            'reference_number' => $payment->reference_number,
            'card_mask' => $payment->card_mask,
            'paid_at' => $payment->paid_at?->toIso8601ZuluString(),
            'occurred_at' => now()->toIso8601ZuluString(),
        ];
    }

    public static function backoffSeconds(int $attempt): int
    {
        $base = (int) config('payments.webhooks.backoff_base_seconds');
        $max = (int) config('payments.webhooks.backoff_max_seconds');

        return (int) min($max, $base * (2 ** max(0, $attempt - 1)));
    }
}
