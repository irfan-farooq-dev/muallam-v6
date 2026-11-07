<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserSlugHistory;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Lets a user pick a new profile slug. The old slug is kept in user_slug_history,
 * so old links redirect and nobody else can claim it.
 */
class UserSlugChanger
{
    public const PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function __construct(private UserSlugGenerator $generator) {}

    /**
     * "Irfan Farooq Dev" -> "irfan-farooq-dev".
     */
    public function normalize(string $input): string
    {
        return Str::slug($input);
    }

    /**
     * Why the user cannot have this slug, or null when they can.
     */
    public function problemWith(User $user, string $slug): ?string
    {
        if ($slug === $user->slug) {
            return 'This is already your link.';
        }

        $length = strlen($slug);

        if ($length < UserSlugGenerator::MIN_LENGTH || $length > UserSlugGenerator::MAX_LENGTH) {
            return 'Use between '.UserSlugGenerator::MIN_LENGTH.' and '.UserSlugGenerator::MAX_LENGTH.' characters.';
        }

        if (! preg_match(self::PATTERN, $slug)) {
            return 'Use lowercase letters, numbers and single hyphens only.';
        }

        if ($this->generator->isReserved($slug)) {
            return 'This word is reserved. Please choose another.';
        }

        if (User::where('slug', $slug)->exists()) {
            return 'This link is already taken.';
        }

        if (UserSlugHistory::where('slug', $slug)->where('user_id', '!=', $user->getKey())->exists()) {
            return 'This link was used before and is not available.';
        }

        return null;
    }

    /**
     * When the user may change their slug again, or null if they may change it now.
     */
    public function nextChangeAllowedAt(User $user): ?CarbonInterface
    {
        if ($user->slug_changed_at === null) {
            return null;
        }

        $next = $user->slug_changed_at->copy()->addDays((int) config('muallam.slug_change_cooldown_days'));

        return $next->isFuture() ? $next : null;
    }

    /**
     * Saves the new slug. Returns an error message instead when it cannot be used.
     */
    public function change(User $user, string $slug): ?string
    {
        if ($problem = $this->problemWith($user, $slug)) {
            return $problem;
        }

        try {
            DB::transaction(function () use ($user, $slug): void {
                // Taking back one of your own old slugs: it is current again, so it leaves the history.
                $user->slugHistory()->where('slug', $slug)->delete();
                $user->slugHistory()->create(['slug' => $user->slug]);

                $user->forceFill(['slug' => $slug, 'slug_changed_at' => now()])->save();
            });
        } catch (UniqueConstraintViolationException) {
            return 'This link was just taken by someone else. Please choose another.';
        }

        return null;
    }
}
