<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserSlugHistory;
use Illuminate\Support\Str;

/**
 * Builds unique profile slugs such as "irfan-farooq", "irfan-farooq-2", ...
 */
class UserSlugGenerator
{
    public const MIN_LENGTH = 3;

    public const MAX_LENGTH = 50;

    /**
     * Longest base slug, leaving room for a "-NNNNN" suffix within MAX_LENGTH.
     */
    private const MAX_BASE_LENGTH = 44;

    public function generate(string $name): string
    {
        $base = $this->baseFrom($name);
        $taken = $this->takenSlugsStartingWith($base);

        if ($this->isUsable($base, $taken)) {
            return $base;
        }

        $suffix = 2;

        while (! $this->isUsable("{$base}-{$suffix}", $taken)) {
            $suffix++;
        }

        return "{$base}-{$suffix}";
    }

    public function isReserved(string $slug): bool
    {
        return in_array($slug, config('muallam.reserved_slugs'), true);
    }

    private function baseFrom(string $name): string
    {
        $base = Str::of($name)->slug()->limit(self::MAX_BASE_LENGTH, '')->trim('-')->toString();

        if ($base === '') {
            return 'user';
        }

        return strlen($base) < self::MIN_LENGTH ? "{$base}-user" : $base;
    }

    /**
     * Current and retired slugs equal to the base or starting with "base-".
     *
     * @return array<string, true>
     */
    private function takenSlugsStartingWith(string $base): array
    {
        $matching = fn ($query) => $query->where('slug', $base)->orWhere('slug', 'like', $base.'-%');

        return User::query()->where($matching)->pluck('slug')
            ->merge(UserSlugHistory::query()->where($matching)->pluck('slug'))
            ->mapWithKeys(fn (string $slug): array => [$slug => true])
            ->all();
    }

    /**
     * @param  array<string, true>  $taken
     */
    private function isUsable(string $slug, array $taken): bool
    {
        return ! isset($taken[$slug]) && ! $this->isReserved($slug);
    }
}
