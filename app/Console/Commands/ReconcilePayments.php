<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Gateways\Contracts\SupportsSettlement;
use App\Gateways\GatewayManager;
use App\Models\Payment;
use App\Payments\VerificationService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Safety net for interrupted flows:
 *  - re-verifies payments whose callback arrived but whose verification failed transiently
 *    or was abandoned mid-way (stale "verifying"),
 *  - retries settlement for paid payments on PSPs that require it.
 */
class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile {--limit=200}';

    protected $description = 'Retry pending verifications and settlements';

    public function handle(VerificationService $verification, GatewayManager $gateways): int
    {
        $limit = (int) $this->option('limit');
        $stale = now()->subSeconds((int) config('payments.stale_verification_seconds'));

        $toVerify = Payment::where(fn ($q) => $q
            ->where('status', PaymentStatus::CallbackReceived->value)
            ->orWhere(fn ($q) => $q->where('status', PaymentStatus::Verifying->value)->where('updated_at', '<', $stale)))
            ->where('updated_at', '>', now()->subDay())
            ->limit($limit)
            ->get();

        foreach ($toVerify as $payment) {
            try {
                $result = $verification->verify($payment, 'scheduler');
                $this->line("{$payment->public_id}: {$result->status->value}");
            } catch (Throwable $e) {
                report($e);
            }
        }

        $toSettle = Payment::with('latestAttempt.provider', 'latestAttempt.merchant')
            ->where('status', PaymentStatus::Paid->value)
            ->whereNull('settled_at')
            ->where('paid_at', '>', now()->subDay())
            ->limit($limit)
            ->get()
            ->filter(fn (Payment $p) => $p->latestAttempt && $gateways->for($p->latestAttempt->provider) instanceof SupportsSettlement);

        foreach ($toSettle as $payment) {
            $verification->settle($payment, $payment->latestAttempt);
        }

        $this->info("Re-verified {$toVerify->count()}, settlement retried for {$toSettle->count()}.");

        return self::SUCCESS;
    }
}
