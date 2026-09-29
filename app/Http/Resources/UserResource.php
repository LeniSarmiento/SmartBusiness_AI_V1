<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'email' => $this->email,
            'memberships' => $this->memberships->map(fn ($membership) => [
                'id' => $membership->id,
                'organization' => $membership->organization->only('id', 'name', 'currency', 'timezone'),
                'role' => $membership->role->name,
                'permissions' => $membership->role->permissions->pluck('name'),
            ]),
        ];
    }
}
