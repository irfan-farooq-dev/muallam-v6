<?php

namespace App\Http\Controllers\Posts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Posts\StoreCommentRequest;
use App\Models\Comment;
use App\Models\Post;
use App\Services\PostComments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    public function __construct(private PostComments $comments) {}

    public function store(StoreCommentRequest $request, Post $post): RedirectResponse
    {
        $parent = null;

        if ($request->filled('parent_id')) {
            $parent = $post->comments()->whereKey($request->integer('parent_id'))->firstOrFail();
            Gate::authorize('reply', $parent);
        } else {
            Gate::authorize('create', [Comment::class, $post]);
        }

        $comment = $this->comments->add($request->user(), $post, $request->validated('body'), $parent);

        return redirect()->to(route('posts.show', $post).'#comment-'.$comment->getKey())
            ->with('success', $parent ? 'Reply posted.' : 'Comment posted.');
    }

    public function destroy(Comment $comment): RedirectResponse
    {
        Gate::authorize('delete', $comment);
        $this->comments->remove($comment);

        return redirect()->to(route('posts.show', $comment->post).'#comments')->with('success', 'Comment deleted.');
    }

    public function visibility(Request $request, Comment $comment): RedirectResponse
    {
        Gate::authorize('moderate', $comment);
        $visible = $request->boolean('visible');
        $this->comments->setVisible($comment, $visible);

        return redirect()->to(route('posts.show', $comment->post).'#comment-'.$comment->getKey())
            ->with('success', $visible ? 'Comment is visible again.' : 'Comment hidden. Only you and admins can see it now.');
    }

    /**
     * Guests who click "Sign in to comment" pass through sign-in and land back at the comments.
     */
    public function signInToComment(Post $post): RedirectResponse
    {
        return redirect()->to(route('posts.show', $post).'#comments');
    }
}
