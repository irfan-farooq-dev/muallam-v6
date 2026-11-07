<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PostStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public const CHART_DAYS = 30;

    private const RECENT = 5;

    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'users' => $this->userStats(),
            'posts' => $this->postStats(),
            'engagement' => $this->engagementStats(),
            'activeCategories' => Category::query()->where('is_active', true)->count(),
            'chart' => $this->chart(),
            'recentUsers' => User::latest()->latest('id')->take(self::RECENT)->get(),
            'recentPosts' => Post::with('user:id,name,slug,avatar')->latest('updated_at')->latest('id')->take(self::RECENT)->get(),
            'recentComments' => Comment::with(['user:id,name,slug,avatar', 'post:id,title,slug'])->latest()->latest('id')->take(self::RECENT)->get(),
        ]);
    }

    /**
     * @return array{total: int, new_this_week: int, unverified: int, inactive: int, admins: int}
     */
    private function userStats(): array
    {
        return [
            'total' => User::count(),
            'new_this_week' => User::where('created_at', '>=', now()->subDays(7))->count(),
            'unverified' => User::whereNull('email_verified_at')->count(),
            'inactive' => User::where('is_active', false)->count(),
            'admins' => User::where('role', UserRole::Admin)->count(),
        ];
    }

    /**
     * @return array{total: int, published: int, scheduled: int, drafts: int, private: int, restricted: int}
     */
    private function postStats(): array
    {
        $now = now();
        $published = PostStatus::Published->value;

        $row = Post::query()->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when status = ? and published_at <= ? then 1 else 0 end) as published', [$published, $now])
            ->selectRaw('sum(case when status = ? and published_at > ? then 1 else 0 end) as scheduled', [$published, $now])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as drafts', [PostStatus::Draft->value])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as private', [PostStatus::Private->value])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as restricted', [PostStatus::Restricted->value])
            ->first();

        return array_map(fn (mixed $value): int => (int) $value, (array) $row);
    }

    /**
     * @return array{views: int, likes: int, comments: int, hidden_comments: int}
     */
    private function engagementStats(): array
    {
        return [
            'views' => (int) Post::sum('views_count'),
            'likes' => (int) Post::sum('likes_count'),
            'comments' => Comment::where('is_approved', true)->count(),
            'hidden_comments' => Comment::where('is_approved', false)->count(),
        ];
    }

    /**
     * New sign-ups and newly published posts per day for the last CHART_DAYS days.
     *
     * @return array{labels: list<string>, signups: list<int>, posts: list<int>}
     */
    private function chart(): array
    {
        $start = now()->subDays(self::CHART_DAYS - 1)->startOfDay();
        $days = collect(range(0, self::CHART_DAYS - 1))->map(fn (int $offset): CarbonInterface => $start->copy()->addDays($offset));

        $perDay = fn (Collection $dates): Collection => $dates->countBy(fn ($date): string => $date->format('Y-m-d'));

        $signups = $perDay(User::where('created_at', '>=', $start)->pluck('created_at'));
        $posts = $perDay(Post::publiclyVisible()->where('published_at', '>=', $start)->pluck('published_at'));

        return [
            'labels' => $days->map(fn (CarbonInterface $day): string => $day->format('j M'))->all(),
            'signups' => $days->map(fn (CarbonInterface $day): int => $signups->get($day->format('Y-m-d'), 0))->all(),
            'posts' => $days->map(fn (CarbonInterface $day): int => $posts->get($day->format('Y-m-d'), 0))->all(),
        ];
    }
}
