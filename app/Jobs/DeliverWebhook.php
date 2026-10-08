<?php

namespace App\Jobs;

use App\Enums\WebhookStatus;
use App\Models\WebhookDelivery;
use App\Payments\PaymentEventRecorder;
use App\Webhooks\WebhookHeaders;
use App\Webhooks\WebhookService;
use App\Webhooks\WebhookSigner;
use App\Webhooks\WebhookUrlGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delivers one webhook attempt. Safe to run concurrently or more than once: a delivery is
 * claimed with a conditional UPDATE, so only one worker sends it at a time and delivered
 * rows are never re-sent. Receivers should still de-duplicate on the delivery id header (see WebhookHeaders).
 */
class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $deliveryId)
    {
        $this->onQueue(config('payments.webhooks.queue'));
    }

    public function handle(PaymentEventRecorder $events): void
    {
        $claimed = WebhookDelivery::whereKey($this->deliveryId)
            ->where('status', WebhookStatus::Pending->value)
            ->where(fn ($q) => $q->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now()))
            ->update(['status' => WebhookStatus::Processing->value, 'updated_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        $delivery = WebhookDelivery::with(['client', 'payment'])->findOrFail($this->deliveryId);
        $attempt = $delivery->attempt + 1;
        $body = json_encode($delivery->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $timestamp = time();
        $secret = (string) $delivery->client->webhook_secret;

        $httpStatus = null;
        $responseBody = null;
        $error = null;

        try {
            if (! WebhookUrlGuard::isAllowed($delivery->endpoint)) {
                throw new \RuntimeException('Webhook endpoint is not allowed (must be public HTTPS).');
            }

            $response = Http::timeout(config('payments.webhooks.timeout'))
                ->withOptions(['allow_redirects' => false])
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => (string) config('payments.webhooks.user_agent'),
                    WebhookHeaders::event() => $delivery->event,
                    WebhookHeaders::deliveryId() => $delivery->public_id,
                    WebhookHeaders::timestamp() => (string) $timestamp,
                    WebhookHeaders::signature() => WebhookSigner::sign($secret, $timestamp, $body),
                    'X-Request-Id' => (string) $delivery->request_id,
                ])
                ->withBody($body, 'application/json')
                ->post($delivery->endpoint);

            $httpStatus = $response->status();
            $responseBody = mb_substr($response->body(), 0, 2000);
            $success = $response->successful();
            $error = $success ? null : "HTTP {$httpStatus}";
        } catch (Throwable $e) {
            $success = false;
            $error = mb_substr($e->getMessage(), 0, 1000);
        }

        DB::transaction(function () use ($delivery, $attempt, $success, $httpStatus, $responseBody, $error, $events) {
            $maxAttempts = (int) config('payments.webhooks.max_attempts');
            $exhausted = ! $success && $attempt >= $maxAttempts;

            $delivery->forceFill([
                'attempt' => $attempt,
                'http_status' => $httpStatus,
                'response_body' => $responseBody,
                'last_error' => $error,
                'status' => $success ? WebhookStatus::Delivered : ($exhausted ? WebhookStatus::Failed : WebhookStatus::Pending),
                'delivered_at' => $success ? now() : null,
                'next_retry_at' => $success || $exhausted ? null : now()->addSeconds(WebhookService::backoffSeconds($attempt)),
            ])->save();

            $events->record($delivery->payment, $success ? 'webhook.delivered' : ($exhausted ? 'webhook.failed' : 'webhook.attempt_failed'), 'webhook', [
                'event' => $delivery->event,
                'delivery_id' => $delivery->public_id,
                'attempt' => $attempt,
                'http_status' => $httpStatus,
                'error' => $error,
            ]);
        });

        Log::info('webhook.attempt', [
            'delivery_id' => $delivery->public_id,
            'event' => $delivery->event,
            'attempt' => $attempt,
            'http_status' => $httpStatus,
            'success' => $success,
        ]);

        if (! $success && $delivery->status === WebhookStatus::Pending) {
            self::dispatch($delivery->id)->delay($delivery->next_retry_at);
        }
    }
}
