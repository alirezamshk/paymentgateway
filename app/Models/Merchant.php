<?php

namespace App\Models;

use App\Enums\RecordStatus;
use App\Support\PublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Merchant extends Model
{
    protected $fillable = [
        'client_id', 'provider_id', 'name', 'merchant_identifier', 'terminal_identifier', 'username',
        'encrypted_password', 'encrypted_api_key', 'encrypted_config', 'status', 'is_default',
    ];

    /** Credentials never leave the model through serialization. */
    protected $hidden = [
        'id', 'client_id', 'provider_id', 'merchant_identifier', 'terminal_identifier', 'username',
        'encrypted_password', 'encrypted_api_key', 'encrypted_config',
    ];

    protected static function booted(): void
    {
        static::creating(function (Merchant $merchant) {
            $merchant->public_id ??= PublicId::make('mer');
        });
    }

    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
            'is_default' => 'boolean',
            'encrypted_password' => 'encrypted',
            'encrypted_api_key' => 'encrypted',
            'encrypted_config' => 'encrypted:array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(GatewayProvider::class, 'provider_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', RecordStatus::Active->value);
    }

    public function isActive(): bool
    {
        return $this->status === RecordStatus::Active;
    }

    /**
     * All PSP credentials as one flat array: the standard columns plus any extra
     * provider-specific keys stored in the encrypted config.
     *
     * @return array<string, mixed>
     */
    public function credentials(): array
    {
        return array_filter([
            'merchant_identifier' => $this->merchant_identifier,
            'terminal_identifier' => $this->terminal_identifier,
            'username' => $this->username,
            'password' => $this->encrypted_password,
            'api_key' => $this->encrypted_api_key,
        ], fn ($v) => $v !== null && $v !== '') + ($this->encrypted_config ?? []);
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        return $this->credentials()[$key] ?? $default;
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
