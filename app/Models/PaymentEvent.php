<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only audit trail of everything that happens to a payment.
 */
class PaymentEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'payment_id', 'payment_attempt_id', 'event', 'old_status', 'new_status', 'source', 'request_id', 'metadata',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Payment events are immutable.'));
        static::deleting(fn () => throw new LogicException('Payment events are immutable.'));
    }

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
