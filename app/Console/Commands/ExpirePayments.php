<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Payments\PaymentStateMachine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpirePayments extends Command
{
    protected $signature = 'payments:expire {--limit=500}';

    protected $description = 'Expire unpaid payments whose expires_at has passed';

    public function handle(PaymentStateMachine $stateMachine): int
    {
        $expirable = [PaymentStatus::Created->value, PaymentStatus::Pending->value, PaymentStatus::Redirected->value];
        $count = 0;

        Payment::whereIn('status', $expirable)
            ->where('expires_at', '<', now())
            ->limit((int) $this->option('limit'))
            ->pluck('id')
            ->each(function (int $id) use ($stateMachine, $expirable, &$count) {
                DB::transaction(function () use ($id, $stateMachine, $expirable, &$count) {
                    $payment = Payment::whereKey($id)->lockForUpdate()->first();

                    // Re-check under lock: a callback may have arrived in the meantime.
                    if ($payment && in_array($payment->status->value, $expirable, true) && $payment->expires_at?->isPast()) {
                        $stateMachine->transition($payment, PaymentStatus::Expired, 'scheduler');
                        $count++;
                    }
                });
            });

        $this->info("Expired {$count} payment(s).");

        return self::SUCCESS;
    }
}
