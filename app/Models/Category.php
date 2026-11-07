<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

#[Fillable(['name', 'slug', 'description', 'sort_order', 'is_active'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    public const NAVIGATION_CACHE_KEY = 'categories.navigation';

    /**
     * Any change to a category refreshes the cached header menu.
     */
    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::NAVIGATION_CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::NAVIGATION_CACHE_KEY));
    }

    /**
     * Active categories for the header menu, cached until a category changes.
     * Stored as plain arrays: the cache is not allowed to rebuild PHP objects (config/cache.php "serializable_classes").
     *
     * @return list<array{name: string, slug: string}>
     */
    public static function forNavigation(): array
    {
        return Cache::rememberForever(
            self::NAVIGATION_CACHE_KEY,
            fn (): array => static::query()->active()->get(['name', 'slug'])
                ->map(fn (Category $category): array => ['name' => $category->name, 'slug' => $category->slug])
                ->all(),
        );
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * Active categories in display order.
     *
     * @param  Builder<Category>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}
