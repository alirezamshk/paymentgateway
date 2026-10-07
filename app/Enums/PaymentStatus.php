<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Created = 'created';
    case Pending = 'pending';
    case Redirected = 'redirected';
    case CallbackReceived = 'callback_received';
    case Verifying = 'verifying';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    /**
     * Allowed transitions. Anything not listed here is rejected by the state machine.
     *
     * - failed -> pending is only used when an explicit new attempt is requested.
     * - verifying -> callback_received is only used when verification hit a transient
     *   PSP/network error and must be retried; the result is still unknown.
     * - paid, cancelled and expired are terminal.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Created => [self::Pending, self::Failed, self::Cancelled, self::Expired],
            self::Pending => [self::Redirected, self::CallbackReceived, self::Failed, self::Cancelled, self::Expired],
            self::Redirected => [self::CallbackReceived, self::Failed, self::Cancelled, self::Expired],
            self::CallbackReceived => [self::Verifying, self::Failed, self::Expired],
            self::Verifying => [self::Paid, self::Failed, self::CallbackReceived],
            self::Failed => [self::Pending],
            self::Paid, self::Cancelled, self::Expired => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Paid, self::Failed, self::Cancelled, self::Expired], true);
    }

    /** States in which the customer may still be sent to the PSP. */
    public function isPayable(): bool
    {
        return in_array($this, [self::Pending, self::Redirected], true);
    }

    /** Webhook event emitted when a payment enters this state, if any. */
    public function webhookEvent(): ?string
    {
        return match ($this) {
            self::Pending => 'payment.pending',
            self::Paid => 'payment.succeeded',
            self::Failed => 'payment.failed',
            self::Cancelled => 'payment.cancelled',
            self::Expired => 'payment.expired',
            default => null,
        };
    }
}
