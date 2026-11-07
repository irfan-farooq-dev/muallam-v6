<?php

namespace App\Services;

use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Likes and unlikes posts while keeping posts.likes_count in step with the likes table.
 */
class PostLikes
{
    /**
     * Flips the like. Returns whether the user now likes the post, and the new count.
     *
     * @return array{liked: bool, likes_count: int}
     */
    public function toggle(User $user, Post $post): array
    {
        return DB::transaction(function () use ($user, $post): array {
            // Lock the post row so two quick clicks cannot both change the counter.
            $locked = Post::whereKey($post->getKey())->lockForUpdate()->firstOrFail();
            $existing = Like::whereBelongsTo($user)->whereBelongsTo($locked)->first();

            if ($existing) {
                $existing->delete();
                Post::whereKey($locked->getKey())->where('likes_count', '>', 0)->toBase()->decrement('likes_count');
                $liked = false;
            } else {
                try {
                    Like::create(['user_id' => $user->getKey(), 'post_id' => $locked->getKey()]);
                    Post::whereKey($locked->getKey())->toBase()->increment('likes_count');
                } catch (UniqueConstraintViolationException) {
                    // Already liked in a parallel request; nothing to add.
                }
                $liked = true;
            }

            return [
                'liked' => $liked,
                'likes_count' => (int) Post::whereKey($locked->getKey())->value('likes_count'),
            ];
        });
    }

    public function likes(?User $user, Post $post): bool
    {
        return $user !== null && Like::whereBelongsTo($user)->whereBelongsTo($post)->exists();
    }
}
