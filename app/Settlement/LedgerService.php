<?php

namespace App\Settlement;

use App\Enums\Currency;
use App\Enums\PaymentStatus;
use App\Exceptions\ApiException;
use App\Models\Client;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Payout;
use App\Services\AuditLogger;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Settlement for clients whose payments land in Tech-Kala's own account.
 *
 *   paid payment  → +amount (payment) and -commission (commission), both in Rials
 *   payout        → -amount (payout), recorded manually after the bank transfer
 *   correction    → ±amount (adjustment)
 *
 * balance owed to a client = SUM(amount_irr); available = entries whose available_at has passed.
 */
class LedgerService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Credit a paid payment (idempotent: a payment is credited at most once).
     * Called inside the transaction that marks the payment paid.
     */
    public function recordPayment(Payment $payment): void
    {
        if ($payment->status !== PaymentStatus::Paid) {
            return;
        }

        if (LedgerEntry::where('payment_id', $payment->id)->where('type', LedgerEntry::TYPE_PAYMENT)->exists()) {
            return;
        }

        $client = $payment->client ?? Client::findOrFail($payment->client_id);
        $amount = Money::convert($payment->amount, $payment->currency, Currency::IRR);
        $commission = $this->commission($client, $amount);
        $paidAt = $payment->paid_at ?? now();
        $availableAt = $paidAt->copy()->addHours($client->settlement_delay_hours);

        try {
            DB::transaction(function () use ($payment, $client, $amount, $commission, $availableAt) {
                LedgerEntry::create([
                    'client_id' => $client->id,
                    'type' => LedgerEntry::TYPE_PAYMENT,
                    'amount_irr' => $amount,
                    'payment_id' => $payment->id,
                    'description' => "Payment {$payment->public_id} / order {$payment->order_id}",
                    'available_at' => $availableAt,
                ]);

                if ($commission > 0) {
                    LedgerEntry::create([
                        'client_id' => $client->id,
                        'type' => LedgerEntry::TYPE_COMMISSION,
                        'amount_irr' => -$commission,
                        'payment_id' => $payment->id,
                        'description' => $this->commissionLabel($client),
                        'available_at' => $availableAt,
                    ]);
                }
            });
        } catch (UniqueConstraintViolationException) {
            // Credited concurrently by another process: nothing to do.
        }
    }

    /** Commission in Rials: floor(amount × bps / 10000) + fixed, never more than the amount. */
    public function commission(Client $client, int $amountIrr): int
    {
        $commission = intdiv($amountIrr * $client->commission_bps, 10_000) + $client->commission_fixed_irr;

        return min($amountIrr, max(0, $commission));
    }

    /**
     * @return array{balance: int, available: int, pending: int, paid_in: int, commission: int, paid_out: int, adjustments: int}
     */
    public function summary(Client $client, ?CarbonInterface $at = null): array
    {
        $at ??= now();
        $rows = LedgerEntry::where('client_id', $client->id)
            ->selectRaw('type, SUM(amount_irr) AS total, SUM(CASE WHEN available_at <= ? THEN amount_irr ELSE 0 END) AS available_total', [$at->toDateTimeString()])
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        $sum = fn (string $type, string $col = 'total') => (int) ($rows[$type]->{$col} ?? 0);
        $balance = (int) $rows->sum(fn ($r) => (int) $r->total);
        $available = (int) $rows->sum(fn ($r) => (int) $r->available_total);

        return [
            'balance' => $balance,
            'available' => min($available, $balance),
            'pending' => max(0, $balance - $available),
            'paid_in' => $sum(LedgerEntry::TYPE_PAYMENT),
            'commission' => -$sum(LedgerEntry::TYPE_COMMISSION),
            'paid_out' => -$sum(LedgerEntry::TYPE_PAYOUT),
            'adjustments' => $sum(LedgerEntry::TYPE_ADJUSTMENT),
        ];
    }

    /** Record a manual bank transfer to the client. Cannot exceed the available balance. */
    public function recordPayout(Client $client, int $amountIrr, string $bankReference, CarbonInterface $paidOn, ?string $note, ?int $adminId): Payout
    {
        if ($amountIrr <= 0) {
            throw new ApiException('INVALID_AMOUNT', 'Payout amount must be positive.', 422);
        }

        $payout = DB::transaction(function () use ($client, $amountIrr, $bankReference, $paidOn, $note, $adminId) {
            // Serialize payouts per client so two admins cannot pay the same balance twice.
            Client::whereKey($client->id)->lockForUpdate()->first();

            $available = $this->summary($client)['available'];

            if ($amountIrr > $available) {
                throw new ApiException('INSUFFICIENT_BALANCE', 'Payout exceeds the available balance ('.number_format($available).' IRR).', 422);
            }

            $payout = Payout::create([
                'client_id' => $client->id,
                'amount_irr' => $amountIrr,
                'iban' => $client->iban,
                'account_holder' => $client->account_holder,
                'bank_reference' => $bankReference,
                'paid_on' => $paidOn->toDateString(),
                'note' => $note,
                'created_by' => $adminId,
            ]);

            LedgerEntry::create([
                'client_id' => $client->id,
                'type' => LedgerEntry::TYPE_PAYOUT,
                'amount_irr' => -$amountIrr,
                'payout_id' => $payout->id,
                'description' => "Payout {$payout->public_id} / bank ref {$bankReference}",
                'available_at' => now(),
                'created_by' => $adminId,
            ]);

            return $payout;
        });

        $this->audit->log('admin', $adminId, 'settlement.payout_recorded', $client->id, 'payout', $payout->public_id, [
            'amount_irr' => $amountIrr,
            'bank_reference' => $bankReference,
        ]);

        return $payout;
    }

    /** Manual correction (positive = owed to the client, negative = owed by the client). */
    public function recordAdjustment(Client $client, int $amountIrr, string $description, ?int $adminId): LedgerEntry
    {
        if ($amountIrr === 0) {
            throw new ApiException('INVALID_AMOUNT', 'Adjustment amount cannot be zero.', 422);
        }

        $entry = LedgerEntry::create([
            'client_id' => $client->id,
            'type' => LedgerEntry::TYPE_ADJUSTMENT,
            'amount_irr' => $amountIrr,
            'description' => $description,
            'available_at' => now(),
            'created_by' => $adminId,
        ]);

        $this->audit->log('admin', $adminId, 'settlement.adjustment_recorded', $client->id, 'ledger_entry', (string) $entry->id, [
            'amount_irr' => $amountIrr,
            'description' => $description,
        ]);

        return $entry;
    }

    /** Credit paid payments that have no ledger entry yet (e.g. paid before settlement existed). */
    public function backfill(?Client $client = null): int
    {
        $count = 0;

        Payment::with('client')
            ->where('status', PaymentStatus::Paid->value)
            ->when($client, fn ($q) => $q->where('client_id', $client->id))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('ledger_entries')
                ->whereColumn('ledger_entries.payment_id', 'payments.id')
                ->where('ledger_entries.type', LedgerEntry::TYPE_PAYMENT))
            ->orderBy('id')
            ->each(function (Payment $payment) use (&$count) {
                $this->recordPayment($payment);
                $count++;
            });

        return $count;
    }

    private function commissionLabel(Client $client): string
    {
        $parts = [];

        if ($client->commission_bps > 0) {
            $parts[] = rtrim(rtrim(number_format($client->commission_bps / 100, 2, '.', ''), '0'), '.').'%';
        }

        if ($client->commission_fixed_irr > 0) {
            $parts[] = number_format($client->commission_fixed_irr).' IRR';
        }

        return 'Commission '.implode(' + ', $parts);
    }
}
