<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;

class CommentPolicy
{
    /**
     * Verified readers can comment on live public posts that allow comments.
     */
    public function create(User $user, Post $post): bool
    {
        return $user->hasVerifiedEmail()
            && $post->allow_comments
            && Post::publiclyVisible()->whereKey($post->getKey())->exists();
    }

    /**
     * Replies go one level deep: only to a visible top-level comment.
     */
    public function reply(User $user, Comment $parent): bool
    {
        return $parent->parent_id === null
            && $parent->is_approved
            && ! $parent->trashed()
            && $this->create($user, $parent->post);
    }

    /**
     * Writers can delete their own comments; admins can delete any.
     */
    public function delete(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->getKey() || $user->isAdmin();
    }

    /**
     * The post's author (and admins) can hide or show comments on it.
     */
    public function moderate(User $user, Comment $comment): bool
    {
        return $comment->post->user_id === $user->getKey() || $user->isAdmin();
    }
}
