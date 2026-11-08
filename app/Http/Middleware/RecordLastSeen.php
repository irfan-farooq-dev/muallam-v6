<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remembers when a signed-in member was last active, for the "online" dot and "Last seen …".
 * Page views and the notification bell's once-a-minute check both count. Written at most once a minute
 * per member, and without touching updated_at.
 */
class RecordLastSeen
{
    private const WRITE_EVERY_SECONDS = 60;

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ($user->last_seen_at === null || $user->last_seen_at->diffInSeconds(now()) >= self::WRITE_EVERY_SECONDS)) {
            $now = now();
            User::query()->whereKey($user->getKey())->toBase()->update(['last_seen_at' => $now]);
            $user->setAttribute('last_seen_at', $now)->syncOriginalAttribute('last_seen_at');
        }

        return $next($request);
    }
}
