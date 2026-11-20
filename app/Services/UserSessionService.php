<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserSessionService
{
    public function revoke(User $user, ?string $exceptSessionId = null): void
    {
        $sessions = DB::table('sessions')->where('user_id', $user->id);
        if ($exceptSessionId) {
            $sessions->where('id', '!=', $exceptSessionId);
        }
        $sessions->delete();
        $user->tokens()->delete();
        $user->forceFill(['remember_token' => Str::random(60)])->save();
    }
}
