<?php

namespace App\Payments;

use App\Enums\PaymentStatus;
use App\Exceptions\InvalidStateTransition;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Webhooks\WebhookService;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The only place where Payment::status changes. Every transition is validated against
 * PaymentStatus::allowedTransitions(), recorded as an immutable event, and - when the new
 * state is client-visible - queues a webhook in the same database transaction.
 *
 * Callers must hold a row lock on the payment (lockForUpdate inside DB::transaction).
 */
class PaymentStateMachine
{
    public function __construct(
        private readonly PaymentEventRecorder $events,
        private readonly WebhookService $webhooks,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  other payment columns to update atomically
     * @param  array<string, mixed>  $metadata
     */
    public function transition(
        Payment $payment,
        PaymentStatus $to,
        string $source,
        array $attributes = [],
        array $metadata = [],
        ?PaymentAttempt $attempt = null,
    ): Payment {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Payment transitions must run inside a database transaction.');
        }

        $from = $payment->status;

        if (! $from->canTransitionTo($to)) {
            throw new InvalidStateTransition($from, $to);
        }

        $payment->fill($attributes);
        $payment->status = $to;
        $payment->save();

        $this->events->record(
            $payment,
            'payment.'.$to->value,
            $source,
            $metadata,
            $attempt,
            $from->value,
            $to->value,
        );

        if ($event = $to->webhookEvent()) {
            $this->webhooks->enqueue($payment, $event);
        }

        return $payment;
    }
}
