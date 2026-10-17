<?php

namespace App\Modules\Platform\Audit\Services;

use App\Models\User;
use App\Modules\Platform\Audit\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function log(
        string $action,
        Model $entity,
        ?User $user = null,
        ?int $organizationId = null,
        ?array $payload = null,
        ?string $ipAddress = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $user?->id,
            'organization_id' => $organizationId,
            'action' => $action,
            'entity_type' => get_class($entity),
            'entity_id' => (int) $entity->getKey(),
            'payload' => $payload,
            'ip_address' => $ipAddress,
            'created_at' => now(),
        ]);
    }
}
