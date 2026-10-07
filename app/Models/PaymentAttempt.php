<?php

namespace App\Models;

use App\Enums\AttemptStatus;
use App\Support\PublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAttempt extends Model
{
    protected $fillable = [
        'payment_id', 'merchant_id', 'provider_id', 'attempt_number', 'status', 'psp_invoice_id',
        'authority', 'token', 'redirect_payload', 'request_payload', 'response_payload',
        'callback_payload', 'verify_payload', 'error_code', 'error_message',
    ];

    protected $hidden = ['id', 'payment_id', 'merchant_id', 'provider_id'];

    protected static function booted(): void
    {
        static::creating(function (PaymentAttempt $attempt) {
            $attempt->public_id ??= PublicId::make('att');
        });
    }

    protected function casts(): array
    {
        return [
            'status' => AttemptStatus::class,
            'psp_invoice_id' => 'integer',
            'redirect_payload' => 'array',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'callback_payload' => 'array',
            'verify_payload' => 'array',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(GatewayProvider::class, 'provider_id');
    }
}
