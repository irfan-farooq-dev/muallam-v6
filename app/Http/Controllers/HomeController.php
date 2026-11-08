<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    private const PER_PAGE = 12;

    /**
     * The slider shows the newest posts that have a featured image, once there are at least FEATURED_MIN of them.
     */
    private const FEATURED_MAX = 5;

    private const FEATURED_MIN = 2;

    /**
     * Category sections: one large and three small posts, for categories with enough posts to fill one.
     */
    private const SECTION_POSTS = 4;

    private const SECTION_MIN_POSTS = 3;

    private const SECTION_MAX = 6;

    /**
     * Columns and relations a post card needs.
     */
    private const CARD_RELATIONS = ['user:id,name,slug,avatar,is_bio_public', 'category:id,name,slug'];

    public function __invoke(Request $request): View
    {
        $posts = Post::publiclyVisible()
            ->with(self::CARD_RELATIONS)
            ->latest('published_at')
            ->latest('id')
            ->paginate(self::PER_PAGE);

        $onFirstPage = $posts->onFirstPage();

        return view('home', [
            'posts' => $posts,
            'featured' => $onFirstPage ? $this->featured() : new Collection,
            'categorySections' => $onFirstPage ? $this->categorySections() : [],
        ]);
    }

    /**
     * @return Collection<int, Post>
     */
    private function featured(): Collection
    {
        $featured = Post::publiclyVisible()
            ->whereNotNull('featured_image')
            ->with(self::CARD_RELATIONS)
            ->latest('published_at')
            ->latest('id')
            ->take(self::FEATURED_MAX)
            ->get();

        return $featured->count() >= self::FEATURED_MIN ? $featured : new Collection;
    }

    /**
     * @return list<array{category: Category, posts: Collection<int, Post>}>
     */
    private function categorySections(): array
    {
        $postCounts = Post::publiclyVisible()
            ->whereNotNull('category_id')
            ->toBase()
            ->selectRaw('category_id, count(*) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $categoryIds = $postCounts->filter(fn (int|string $total): bool => (int) $total >= self::SECTION_MIN_POSTS)->keys();

        if ($categoryIds->isEmpty()) {
            return [];
        }

        return Category::query()
            ->active()
            ->whereKey($categoryIds)
            ->take(self::SECTION_MAX)
            ->get(['id', 'name', 'slug'])
            ->map(fn (Category $category): array => [
                'category' => $category,
                'posts' => Post::publiclyVisible()
                    ->whereBelongsTo($category)
                    ->with(self::CARD_RELATIONS)
                    ->latest('published_at')
                    ->latest('id')
                    ->take(self::SECTION_POSTS)
                    ->get(),
            ])
            ->all();
    }
}
