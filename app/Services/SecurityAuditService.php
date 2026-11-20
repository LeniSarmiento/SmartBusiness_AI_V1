<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\SecurityAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SecurityAuditService
{
    public function record(
        Organization $organization,
        ?User $actor,
        string $event,
        Model $subject,
        ?array $before = null,
        ?array $after = null,
        ?array $metadata = null,
    ): SecurityAuditLog {
        $request = app()->runningInConsole() ? null : request();

        return SecurityAuditLog::create([
            'organization_id' => $organization->id,
            'actor_id' => $actor?->id,
            'event' => $event,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'before' => $before,
            'after' => $after,
            'metadata' => $metadata,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 500) : null,
            'created_at' => now(),
        ]);
    }
}
