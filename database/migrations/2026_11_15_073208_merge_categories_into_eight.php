<?php

use App\Models\Category;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Twelve topic categories become seven (plus General). For each new category, the first old one that exists is
 * renamed (so its posts stay put), posts in the other old ones move into it, and the emptied old ones are removed.
 * Old /category/... addresses redirect to the new ones (routes/web.php). On a fresh database there is nothing to
 * merge; CategorySeeder creates the list.
 */
return new class extends Migration
{
    /**
     * New slug => [new name, old slugs merged into it].
     *
     * @var array<string, array{string, list<string>}>
     */
    public const MERGES = [
        'science-technology' => ['Science & Technology', ['science', 'technology']],
        'health-lifestyle' => ['Health & Lifestyle', ['health-wellness', 'lifestyle']],
        'culture-art-travel' => ['Culture, Art & Travel', ['travel-culture', 'art-creativity']],
        'entertainment-sports' => ['Entertainment & Sports', ['entertainment', 'sports']],
        'general' => ['General', ['education']],
    ];

    public function up(): void
    {
        foreach (self::MERGES as $slug => [$name, $oldSlugs]) {
            DB::transaction(function () use ($slug, $name, $oldSlugs): void {
                $targetId = DB::table('categories')->where('slug', $slug)->value('id');
                $oldIds = DB::table('categories')->whereIn('slug', $oldSlugs)->orderBy('sort_order')->pluck('id');

                if ($targetId === null) {
                    $targetId = $oldIds->shift();

                    if ($targetId === null) {
                        return;
                    }

                    DB::table('categories')->where('id', $targetId)->update(['name' => $name, 'slug' => $slug, 'updated_at' => now()]);
                }

                $oldIds = $oldIds->reject(fn ($id): bool => (int) $id === (int) $targetId);

                if ($oldIds->isNotEmpty()) {
                    DB::table('posts')->whereIn('category_id', $oldIds)->update(['category_id' => $targetId]);
                    DB::table('categories')->whereIn('id', $oldIds)->delete();
                }
            });
        }

        Cache::forget(Category::NAVIGATION_CACHE_KEY);
    }

    /**
     * Merged categories cannot be split again: which post came from which old category is not kept.
     */
    public function down(): void
    {
        //
    }
};
