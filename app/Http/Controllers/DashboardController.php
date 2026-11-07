<?php

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const RECENT_POSTS = 5;

    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('member.dashboard', [
            'stats' => $this->postStats($user),
            'recentPosts' => $user->posts()
                ->with('category:id,name')
                ->latest('updated_at')
                ->latest('id')
                ->take(self::RECENT_POSTS)
                ->get(),
        ]);
    }

    /**
     * Counts of the user's posts by visibility, plus totals of their engagement counters, in one query.
     *
     * @return array{total: int, published: int, scheduled: int, drafts: int, private: int, views: int, likes: int, comments: int}
     */
    private function postStats(User $user): array
    {
        $now = now();
        $published = PostStatus::Published->value;

        $row = Post::query()->whereBelongsTo($user)->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when status = ? and published_at <= ? then 1 else 0 end) as published', [$published, $now])
            ->selectRaw('sum(case when status = ? and published_at > ? then 1 else 0 end) as scheduled', [$published, $now])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as drafts', [PostStatus::Draft->value])
            ->selectRaw('sum(case when status in (?, ?) then 1 else 0 end) as private', [PostStatus::Private->value, PostStatus::Restricted->value])
            ->selectRaw('sum(views_count) as views')
            ->selectRaw('sum(likes_count) as likes')
            ->selectRaw('sum(comments_count) as comments')
            ->first();

        return array_map(fn (mixed $value): int => (int) $value, (array) $row);
    }
}
