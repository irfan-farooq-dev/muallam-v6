<?php

namespace App\Policies;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    /**
     * Writing needs a verified email address.
     */
    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail();
    }

    /**
     * Published posts are public once their publish time has passed.
     * Drafts, scheduled, private and restricted posts are only for their author and admins
     * (restricted sharing with invited readers arrives with its own feature).
     */
    public function view(?User $user, Post $post): bool
    {
        if ($post->status === PostStatus::Published && ! $post->isScheduled() && $post->published_at !== null) {
            return true;
        }

        return $user !== null && $this->ownsOrAdministers($user, $post);
    }

    /**
     * Readers can like live public posts, but not their own.
     */
    public function like(User $user, Post $post): bool
    {
        return $post->user_id !== $user->getKey()
            && Post::publiclyVisible()->whereKey($post->getKey())->exists();
    }

    public function update(User $user, Post $post): bool
    {
        return $this->ownsOrAdministers($user, $post);
    }

    public function delete(User $user, Post $post): bool
    {
        return $this->ownsOrAdministers($user, $post);
    }

    private function ownsOrAdministers(User $user, Post $post): bool
    {
        return $post->user_id === $user->getKey() || $user->isAdmin();
    }
}
