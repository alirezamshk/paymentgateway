<?php

namespace App\Models;

use App\Support\PublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A manual bank transfer from Tech-Kala to a client (recorded by an admin).
 */
class Payout extends Model
{
    protected $fillable = [
        'client_id', 'amount_irr', 'iban', 'account_holder', 'bank_reference', 'paid_on', 'note', 'created_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (Payout $payout) {
            $payout->public_id ??= PublicId::make('po');
        });
    }

    protected function casts(): array
    {
        return ['amount_irr' => 'integer', 'paid_on' => 'date'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
