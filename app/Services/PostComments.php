<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Adds, removes and hides comments while keeping posts.comments_count equal to
 * the number of visible (approved, not deleted) comments, replies included.
 */
class PostComments
{
    public function add(User $author, Post $post, string $body, ?Comment $parent = null): Comment
    {
        return DB::transaction(function () use ($author, $post, $body, $parent): Comment {
            $comment = new Comment(['body' => $body, 'parent_id' => $parent?->getKey()]);
            $comment->post()->associate($post);
            $comment->user()->associate($author);
            $comment->save();

            $this->adjustCount($post, +1);

            return $comment;
        });
    }

    public function remove(Comment $comment): void
    {
        DB::transaction(function () use ($comment): void {
            if ($comment->is_approved) {
                $this->adjustCount($comment->post, -1);
            }

            $comment->delete();
        });
    }

    public function setVisible(Comment $comment, bool $visible): void
    {
        if ($comment->is_approved === $visible) {
            return;
        }

        DB::transaction(function () use ($comment, $visible): void {
            $comment->forceFill(['is_approved' => $visible])->save();
            $this->adjustCount($comment->post, $visible ? +1 : -1);
        });
    }

    /**
     * The conversation under a post: top-level comments oldest first, each with its replies.
     * Hidden comments are included only for moderators. A deleted comment stays as a
     * "removed" placeholder while it still has visible replies.
     *
     * @return Collection<int, Comment>
     */
    public function thread(Post $post, bool $includeHidden): Collection
    {
        $visibility = fn ($query) => $includeHidden ? $query : $query->where('is_approved', true);

        return $post->comments()
            ->withTrashed()
            ->whereNull('parent_id')
            ->tap($visibility)
            ->with([
                'user:id,name,slug,avatar,is_bio_public',
                'replies' => fn ($query) => $visibility($query)->with('user:id,name,slug,avatar,is_bio_public')->oldest()->oldest('id'),
            ])
            ->oldest()
            ->oldest('id')
            ->get()
            ->reject(fn (Comment $comment): bool => $comment->trashed() && $comment->replies->isEmpty())
            // The permission checks in the view need the post; give it to every comment instead of re-loading it each time.
            ->each(function (Comment $comment) use ($post): void {
                $comment->setRelation('post', $post);
                $comment->replies->each->setRelation('post', $post);
            })
            ->values();
    }

    private function adjustCount(Post $post, int $by): void
    {
        $query = Post::whereKey($post->getKey())->toBase();

        $by > 0 ? $query->increment('comments_count', $by) : $query->where('comments_count', '>', 0)->decrement('comments_count', -$by);
    }
}
