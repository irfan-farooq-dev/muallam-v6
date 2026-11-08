<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\PostImage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Permanently deletes a user and everything that belongs to them.
 *
 * The database removes their posts, likes, comments, invites and image records by itself (cascades),
 * but it cannot fix the like/comment counters on OTHER people's posts, nor delete uploaded files.
 * Both are handled here.
 */
class UserDeletion
{
    /**
     * @return array{posts: int, likes: int, comments: int}
     */
    public function delete(User $user): array
    {
        $summary = [
            'posts' => $user->posts()->count(),
            'likes' => $user->likes()->count(),
            'comments' => $user->comments()->withTrashed()->count(),
        ];

        // Other people's posts whose counters change once this user's likes and comments disappear.
        $affectedPostIds = Like::whereBelongsTo($user)->pluck('post_id')
            ->merge(Comment::withTrashed()->whereBelongsTo($user)->pluck('post_id'))
            // Replies by others under this user's comments are removed by the cascade too.
            ->merge(Comment::withTrashed()->whereIn('parent_id', Comment::withTrashed()->whereBelongsTo($user)->select('id'))->pluck('post_id'))
            ->unique()
            ->values();

        $files = $this->filesOf($user);

        DB::transaction(function () use ($user, $affectedPostIds): void {
            // The notifications table has no foreign key (it is shared by any notifiable model), so clear them here.
            $user->notifications()->delete();
            $user->delete();
            $this->recount($affectedPostIds->all());
        });

        Storage::disk('public')->delete($files);

        return $summary;
    }

    /**
     * Avatar, featured images and images inside posts (including uploads never used in a post).
     *
     * @return list<string>
     */
    private function filesOf(User $user): array
    {
        return collect([$user->avatar])
            ->merge($user->posts()->pluck('featured_image'))
            ->merge($user->postImages()->pluck('path'))
            ->merge(PostImage::whereIn('post_id', $user->posts()->select('id'))->pluck('path'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $postIds
     */
    private function recount(array $postIds): void
    {
        Post::whereIn('id', $postIds)->get(['id'])->each(function (Post $post): void {
            Post::whereKey($post->getKey())->toBase()->update([
                'likes_count' => $post->likes()->count(),
                'comments_count' => $post->comments()->where('is_approved', true)->count(),
            ]);
        });
    }
}
