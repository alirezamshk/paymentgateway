<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\PaymentStatus;
use App\Support\PublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Status must only be changed through App\Payments\PaymentStateMachine.
 */
class Payment extends Model
{
    protected $fillable = [
        'client_id', 'merchant_id', 'provider_id', 'order_id', 'idempotency_key', 'request_hash',
        'amount', 'currency', 'description', 'customer_mobile', 'customer_username', 'customer_name', 'payment_url', 'authority', 'token',
        'reference_number', 'trace_number', 'card_mask', 'callback_url', 'return_url', 'metadata',
        'attempts_count', 'paid_at', 'settled_at', 'expires_at',
    ];

    protected $hidden = ['id', 'client_id', 'merchant_id', 'provider_id', 'request_hash'];

    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            $payment->public_id ??= PublicId::make('pay');
        });

        // Keep the searchable last 4 card digits in sync with the masked PAN.
        static::saving(function (Payment $payment) {
            if ($payment->isDirty('card_mask')) {
                $digits = preg_replace('/\D/', '', (string) $payment->card_mask) ?? '';
                $payment->card_last4 = strlen($digits) >= 4 ? substr($digits, -4) : null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'is_test' => 'boolean',
            'currency' => Currency::class,
            'status' => PaymentStatus::class,
            'metadata' => 'array',
            'attempts_count' => 'integer',
            'paid_at' => 'datetime',
            'settled_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function merchant(): BelongsTo
    {
        // Archived (soft-deleted) merchants stay visible on their payments.
        return $this->belongsTo(Merchant::class)->withTrashed();
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(GatewayProvider::class, 'provider_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class)->orderBy('attempt_number');
    }

    public function latestAttempt(): HasOne
    {
        return $this->hasOne(PaymentAttempt::class)->ofMany('attempt_number', 'max');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class)->orderBy('id');
    }

    public function webhookDeliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class)->orderBy('id');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
