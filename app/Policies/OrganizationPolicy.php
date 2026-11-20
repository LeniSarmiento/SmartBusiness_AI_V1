<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function permission(User $user, Organization $organization, string $permission): bool
    {
        return $user->memberships()
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->whereHas('role.permissions', fn ($query) => $query->where('name', $permission))
            ->exists();
    }
}
