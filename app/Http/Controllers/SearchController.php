<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SearchController extends Controller
{
    public const MIN_LENGTH = 2;

    private const PER_PAGE = 12;

    /**
     * Searches public posts by title, summary and content. Every word must match somewhere.
     */
    public function __invoke(Request $request): View
    {
        $query = Str::squish(Str::limit((string) $request->query('q'), 100, ''));
        $words = collect(explode(' ', $query))->filter(fn (string $word): bool => mb_strlen($word) >= self::MIN_LENGTH)->take(5);

        $posts = $words->isEmpty()
            ? null
            : Post::publiclyVisible()
                ->with(['user:id,name,slug,avatar,is_bio_public', 'category:id,name,slug'])
                ->where(function (Builder $builder) use ($words): void {
                    foreach ($words as $word) {
                        $like = '%'.addcslashes($word, '%_\\').'%';
                        $builder->where(fn (Builder $match) => $match
                            ->where('title', 'like', $like)
                            ->orWhere('summary', 'like', $like)
                            ->orWhere('body', 'like', $like));
                    }
                })
                ->latest('published_at')
                ->paginate(self::PER_PAGE)
                ->withQueryString();

        return view('search', ['query' => $query, 'posts' => $posts]);
    }
}
