<?php

namespace App\Http\Controllers\Posts;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The public post page.
 */
class PostController extends Controller
{
    private const RELATED_POSTS = 3;

    public function show(Request $request, Post $post): View
    {
        // Hidden posts look like they do not exist to anyone who may not see them.
        abort_unless(Gate::allows('view', $post), 404);

        $post->load(['user', 'category']);
        $isPublic = Post::publiclyVisible()->whereKey($post->getKey())->exists();

        if ($isPublic) {
            $this->countView($request, $post);
        }

        return view('posts.show', [
            'post' => $post,
            'isPublic' => $isPublic,
            'related' => $this->related($post),
            'moreFromAuthor' => $this->moreFromAuthor($post),
        ]);
    }

    /**
     * One view per visitor session; authors reading their own post are not counted.
     */
    private function countView(Request $request, Post $post): void
    {
        if ($request->user()?->is($post->user)) {
            return;
        }

        $viewed = $request->session()->get('viewed_posts', []);

        if (in_array($post->getKey(), $viewed, true)) {
            return;
        }

        // Query-builder increment, so the post's "updated" time is not touched.
        Post::whereKey($post->getKey())->toBase()->increment('views_count');
        $post->views_count++;

        $request->session()->put('viewed_posts', [...$viewed, $post->getKey()]);
    }

    /**
     * @return Collection<int, Post>
     */
    private function related(Post $post)
    {
        if (! $post->category_id) {
            return Post::query()->whereRaw('1 = 0')->get();
        }

        return Post::publiclyVisible()
            ->whereBelongsTo($post->category)
            ->whereKeyNot($post->getKey())
            ->with('user:id,name,slug,avatar,is_bio_public')
            ->latest('published_at')
            ->take(self::RELATED_POSTS)
            ->get();
    }

    /**
     * @return Collection<int, Post>
     */
    private function moreFromAuthor(Post $post)
    {
        return Post::publiclyVisible()
            ->whereBelongsTo($post->user)
            ->whereKeyNot($post->getKey())
            ->latest('published_at')
            ->take(self::RELATED_POSTS)
            ->get(['id', 'title', 'slug', 'published_at']);
    }
}
