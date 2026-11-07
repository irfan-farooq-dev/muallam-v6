<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Signs a user out on other devices after a password change or reset.
 */
class UserSessions
{
    /**
     * Ends the user's stored sessions (except $keepSessionId) and replaces the
     * remember-me token, so old "remember me" cookies stop working too.
     */
    public function endOtherSessions(User $user, ?string $keepSessionId = null): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->save();

        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->when($keepSessionId, fn ($query) => $query->where('id', '!=', $keepSessionId))
            ->delete();
    }
}
