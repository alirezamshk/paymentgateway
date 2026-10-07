<?php

namespace App\Models;

use App\Enums\WebhookStatus;
use App\Support\PublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    protected $fillable = [
        'client_id', 'payment_id', 'event', 'dedupe_key', 'endpoint', 'payload', 'attempt', 'status',
        'http_status', 'response_body', 'last_error', 'request_id', 'next_retry_at', 'delivered_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (WebhookDelivery $delivery) {
            $delivery->public_id ??= PublicId::make('whd');
        });
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => WebhookStatus::class,
            'attempt' => 'integer',
            'http_status' => 'integer',
            'next_retry_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
