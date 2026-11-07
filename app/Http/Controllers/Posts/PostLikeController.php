<?php

namespace App\Http\Controllers\Posts;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\PostLikes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PostLikeController extends Controller
{
    /**
     * Like or unlike. Answers JSON for the button; a plain form post (no JavaScript) is sent back to the post.
     */
    public function toggle(Request $request, Post $post, PostLikes $likes): JsonResponse|RedirectResponse
    {
        $result = $likes->toggle($request->user(), $post);

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        return redirect()->route('posts.show', $post);
    }

    /**
     * Guests who click "Like" are sent through sign-in (the auth middleware remembers this address) and then land back on the post.
     */
    public function signInToLike(Post $post): RedirectResponse
    {
        return redirect()->route('posts.show', $post)->with('info', 'You are signed in. You can like this post now.');
    }
}
