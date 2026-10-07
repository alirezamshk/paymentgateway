<?php

namespace App\Models;

use App\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GatewayProvider extends Model
{
    protected $fillable = ['name', 'code', 'status', 'config'];

    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
            'config' => 'array',
        ];
    }

    public function merchants(): HasMany
    {
        return $this->hasMany(Merchant::class, 'provider_id');
    }

    public function isActive(): bool
    {
        return $this->status === RecordStatus::Active;
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->config ?? [], $key, $default);
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
