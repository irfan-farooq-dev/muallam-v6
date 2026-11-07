<?php

namespace App\Services;

use App\Models\Post;
use Illuminate\Support\Str;

/**
 * Unique post slugs: "my-first-post", then "my-first-post-2", ... Set once when a post is created.
 */
class PostSlugGenerator
{
    /**
     * Leaves room for a "-NNNNNN" suffix within the 220-character column.
     */
    private const MAX_BASE_LENGTH = 200;

    /**
     * Words used by other /posts/... addresses, so a post can never take them.
     */
    private const RESERVED = ['create', 'preview', 'images'];

    public function generate(string $title): string
    {
        $base = Str::of($title)->slug()->limit(self::MAX_BASE_LENGTH, '')->trim('-')->toString() ?: 'post';

        $taken = Post::query()
            ->where(fn ($query) => $query->where('slug', $base)->orWhere('slug', 'like', $base.'-%'))
            ->pluck('slug')
            ->flip();

        if (! $taken->has($base) && ! in_array($base, self::RESERVED, true)) {
            return $base;
        }

        $suffix = 2;

        while ($taken->has("{$base}-{$suffix}")) {
            $suffix++;
        }

        return "{$base}-{$suffix}";
    }
}
