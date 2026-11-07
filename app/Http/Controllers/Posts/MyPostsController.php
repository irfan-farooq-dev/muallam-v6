<?php

namespace App\Http\Controllers\Posts;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyPostsController extends Controller
{
    private const PER_PAGE = 15;

    /**
     * Filter tabs: key => label.
     *
     * @var array<string, string>
     */
    public const FILTERS = [
        'all' => 'All',
        'published' => 'Published',
        'scheduled' => 'Scheduled',
        'draft' => 'Drafts',
        'private' => 'Private',
    ];

    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $filter = array_key_exists($request->query('status'), self::FILTERS) ? $request->query('status') : 'all';
        $search = trim((string) $request->query('search'));

        $posts = $user->posts()
            ->with('category:id,name')
            ->tap(fn (Builder $query) => $this->applyFilter($query, $filter))
            ->when($search !== '', fn (Builder $query) => $query->where('title', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->latest('updated_at')
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $counts = collect(self::FILTERS)->map(fn (string $label, string $key): int => $this->applyFilter($user->posts()->getQuery(), $key)->count());

        return view('member.posts.index', compact('posts', 'filter', 'search', 'counts'));
    }

    private function applyFilter(Builder $query, string $filter): Builder
    {
        $now = now();

        return match ($filter) {
            'published' => $query->where('status', PostStatus::Published)->where('published_at', '<=', $now),
            'scheduled' => $query->where('status', PostStatus::Published)->where('published_at', '>', $now),
            'draft' => $query->where('status', PostStatus::Draft),
            'private' => $query->whereIn('status', [PostStatus::Private, PostStatus::Restricted]),
            default => $query,
        };
    }
}
