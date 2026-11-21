<?php

namespace App\Policies;

use App\Models\AiConversation;
use App\Models\User;

class AiConversationPolicy
{
    public function view(User $user, AiConversation $conversation): bool
    {
        return $conversation->user_id === $user->id
            && $user->memberships()
                ->where('organization_id', $conversation->organization_id)
                ->where('active', true)
                ->whereHas('role.permissions', fn ($query) => $query->where('name', 'reports.read'))
                ->exists();
    }

    public function update(User $user, AiConversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }
}
