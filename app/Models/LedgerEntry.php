<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only settlement ledger. Amounts are signed Rials: positive = owed to the client.
 * Corrections are new 'adjustment' entries, never edits.
 */
class LedgerEntry extends Model
{
    public const UPDATED_AT = null;

    public const TYPE_PAYMENT = 'payment';

    public const TYPE_COMMISSION = 'commission';

    public const TYPE_PAYOUT = 'payout';

    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'client_id', 'type', 'amount_irr', 'payment_id', 'payout_id', 'description', 'available_at', 'created_by',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Ledger entries are immutable.'));
        static::deleting(fn () => throw new LogicException('Ledger entries are immutable.'));
    }

    protected function casts(): array
    {
        return ['amount_irr' => 'integer', 'available_at' => 'datetime', 'created_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }
}
