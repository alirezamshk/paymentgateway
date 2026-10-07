<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Support\RequestContext;
use App\Support\SensitiveData;

class AuditLogger
{
    /**
     * @param  'admin'|'client'|'system'  $actorType
     * @param  array<string, mixed>  $metadata  masked before storage
     */
    public function log(
        string $actorType,
        ?int $actorId,
        string $action,
        ?int $clientId = null,
        ?string $targetType = null,
        ?string $targetId = null,
        array $metadata = [],
    ): AuditLog {
        return AuditLog::create([
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'client_id' => $clientId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'ip' => app()->runningInConsole() ? null : request()->ip(),
            'request_id' => RequestContext::id(),
            'metadata' => SensitiveData::mask($metadata) ?: null,
        ]);
    }
}
