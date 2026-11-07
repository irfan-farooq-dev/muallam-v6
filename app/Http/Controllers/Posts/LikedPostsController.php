<?php

namespace App\Http\Controllers\Posts;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Posts the signed-in user has liked, most recently liked first.
 */
class LikedPostsController extends Controller
{
    private const PER_PAGE = 12;

    public function __invoke(Request $request): View
    {
        $posts = Post::publiclyVisible()
            ->join('likes', 'likes.post_id', '=', 'posts.id')
            ->where('likes.user_id', $request->user()->getKey())
            ->select('posts.*', 'likes.created_at as liked_at')
            ->with(['user:id,name,slug,avatar,is_bio_public', 'category:id,name,slug'])
            ->orderByDesc('likes.created_at')
            ->orderByDesc('likes.id')
            ->paginate(self::PER_PAGE);

        return view('member.likes', ['posts' => $posts]);
    }
}
