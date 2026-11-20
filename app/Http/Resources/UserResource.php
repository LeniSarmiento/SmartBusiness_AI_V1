<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified' => ! is_null($this->email_verified_at),
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'memberships' => $this->memberships->where('active', true)->values()->map(fn ($membership) => [
                'id' => $membership->id,
                'organization' => $membership->organization->only('id', 'name', 'currency', 'timezone'),
                'role' => $membership->role->name,
                'permissions' => $membership->role->permissions->pluck('name'),
                'active' => (bool) $membership->active,
            ]),
        ];
    }
}
