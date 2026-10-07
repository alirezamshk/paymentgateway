<?php

namespace App\Models;

use App\Enums\CredentialStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientCredential extends Model
{
    protected $fillable = ['client_id', 'key_id', 'encrypted_secret', 'status', 'last_used_at', 'revoked_at'];

    protected $hidden = ['encrypted_secret'];

    protected function casts(): array
    {
        return [
            'status' => CredentialStatus::class,
            'encrypted_secret' => 'encrypted',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isActive(): bool
    {
        return $this->status === CredentialStatus::Active;
    }

    public function secret(): string
    {
        return (string) $this->encrypted_secret;
    }
}
