<?php

namespace App\Models;

use App\Enums\RecordStatus;
use App\Support\PublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = ['name', 'slug', 'status', 'webhook_url', 'webhook_secret', 'return_url'];

    protected $hidden = ['id', 'webhook_secret'];

    protected static function booted(): void
    {
        static::creating(function (Client $client) {
            $client->public_id ??= PublicId::make('cli');
        });
    }

    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
            'webhook_secret' => 'encrypted',
        ];
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(ClientCredential::class);
    }

    public function merchants(): HasMany
    {
        return $this->hasMany(Merchant::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function webhookDeliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function isActive(): bool
    {
        return $this->status === RecordStatus::Active;
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
