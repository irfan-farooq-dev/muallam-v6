<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Services\FeaturedImageProcessor;
use App\Services\Notifier;
use App\Services\PostImageStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Every post from every author. Editing itself uses the normal editor (admins may edit any post).
 */
class PostController extends Controller
{
    private const PER_PAGE = 20;

    /**
     * @var array<string, string>
     */
    public const STATUS_FILTERS = [
        'published' => 'Published',
        'scheduled' => 'Scheduled',
        'draft' => 'Draft',
        'private' => 'Private',
        'restricted' => 'Restricted',
    ];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $author = trim((string) $request->query('author'));
        $status = array_key_exists((string) $request->query('status'), self::STATUS_FILTERS) ? (string) $request->query('status') : '';
        $categoryId = $request->integer('category') ?: null;

        $posts = Post::query()
            ->with(['user:id,name,slug,avatar', 'category:id,name,slug'])
            ->withCount(['adminEdits as pending_admin_edits_count' => fn (Builder $query) => $query->whereNull('resolved_at')])
            ->when($search !== '', fn (Builder $query) => $query->where('title', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->when($author !== '', function (Builder $query) use ($author): void {
                $like = '%'.addcslashes($author, '%_\\').'%';
                $query->whereHas('user', fn (Builder $user) => $user->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->when($categoryId, fn (Builder $query) => $query->where('category_id', $categoryId))
            ->when($status !== '', fn (Builder $query) => $this->applyStatus($query, $status))
            ->latest('updated_at')
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.posts.index', [
            'posts' => $posts,
            'categories' => Category::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'filters' => ['search' => $search, 'author' => $author, 'status' => $status, 'category' => $categoryId],
        ]);
    }

    /**
     * Takes a post off the site by making it a draft again (the author can publish it later).
     */
    public function unpublish(Request $request, Post $post, Notifier $notifier): RedirectResponse
    {
        $wasDraft = $post->status === PostStatus::Draft;
        $post->forceFill(['status' => PostStatus::Draft, 'published_at' => null])->save();

        if (! $wasDraft) {
            $notifier->postUnpublishedByAdmin($post, $request->user());
        }

        return back()->with('success', "\"{$post->title}\" is now a draft and no longer public.");
    }

    public function destroy(Request $request, Post $post, FeaturedImageProcessor $featuredImages, PostImageStore $bodyImages, Notifier $notifier): RedirectResponse
    {
        $notifier->postDeletedByAdmin($post, $request->user());

        $featuredImages->delete($post->featured_image);
        $bodyImages->deleteAllFor($post);
        $post->delete();

        return redirect()->route('admin.posts.index')->with('success', "\"{$post->title}\" was deleted.");
    }

    private function applyStatus(Builder $query, string $status): void
    {
        $now = now();

        match ($status) {
            'published' => $query->where('status', PostStatus::Published)->where('published_at', '<=', $now),
            'scheduled' => $query->where('status', PostStatus::Published)->where('published_at', '>', $now),
            default => $query->where('status', $status),
        };
    }
}
