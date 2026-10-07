<?php

namespace App\Console\Commands;

use App\Enums\WebhookStatus;
use App\Jobs\DeliverWebhook;
use App\Models\WebhookDelivery;
use Illuminate\Console\Command;

/**
 * Safety net for webhook delivery: dispatches due retries and recovers deliveries left in
 * "processing" by a crashed worker. Duplicate dispatches are harmless (the job claims rows).
 */
class DispatchDueWebhooks extends Command
{
    protected $signature = 'webhooks:dispatch-due {--limit=500}';

    protected $description = 'Dispatch webhook deliveries that are due for (re)delivery';

    public function handle(): int
    {
        $recovered = WebhookDelivery::where('status', WebhookStatus::Processing->value)
            ->where('updated_at', '<', now()->subMinutes(10))
            ->update(['status' => WebhookStatus::Pending->value, 'next_retry_at' => now()]);

        $due = WebhookDelivery::where('status', WebhookStatus::Pending->value)
            ->where(fn ($q) => $q->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now()))
            ->limit((int) $this->option('limit'))
            ->pluck('id');

        $due->each(fn (int $id) => DeliverWebhook::dispatch($id));

        $this->info("Dispatched {$due->count()} webhook(s), recovered {$recovered} stuck.");

        return self::SUCCESS;
    }
}
