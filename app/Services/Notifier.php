<?php

namespace App\Services;

use App\Enums\NotificationKind;
use App\Enums\UserRole;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Notifications\SiteNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

/**
 * Decides who hears about what, and words it. Nobody is told about their own actions,
 * and nobody gets two notifications for the same event.
 */
class Notifier
{
    private const EXCERPT_LENGTH = 120;

    /**
     * "Ali liked your post". Further likes before the author reads it update the same notification
     * ("Ali and 2 others liked your post") instead of adding more.
     */
    public function postLiked(User $liker, Post $post): void
    {
        $author = $post->user;

        if ($author === null || $author->is($liker)) {
            return;
        }

        $existing = $this->unreadAbout($author, NotificationKind::PostLiked, $post);
        $actorIds = array_values(array_unique([$liker->getKey(), ...($existing?->data['actor_ids'] ?? [])]));
        $others = count($actorIds) - 1;
        $who = $liker->name.match (true) {
            $others === 0 => '',
            $others === 1 => ' and 1 other',
            default => " and {$others} others",
        };

        $notification = new SiteNotification(
            NotificationKind::PostLiked,
            "{$who} liked your post “{$post->title}”",
            url: $this->path('posts.show', $post),
            extra: ['post_id' => $post->getKey(), 'actor_ids' => $actorIds],
        );

        $this->sendOrReplace($author, $notification, $existing);
    }

    /**
     * A new comment or reply: the replied-to writer, the post's author and the admins each hear about it once.
     */
    public function commentPosted(Comment $comment): void
    {
        $comment->loadMissing(['user', 'post.user', 'parent.user']);
        $actor = $comment->user;
        $post = $comment->post;
        $excerpt = Str::limit($comment->body, self::EXCERPT_LENGTH);
        $url = $this->path('posts.show', $post).'#comment-'.$comment->getKey();
        $told = [$actor->getKey()];

        $parentWriter = $comment->parent?->user;
        if ($parentWriter && ! in_array($parentWriter->getKey(), $told, true)) {
            $parentWriter->notify(new SiteNotification(
                NotificationKind::CommentReplied,
                "{$actor->name} replied to your comment on “{$post->title}”",
                $excerpt,
                $url,
                ['post_id' => $post->getKey(), 'comment_id' => $comment->getKey()],
            ));
            $told[] = $parentWriter->getKey();
        }

        $author = $post->user;
        if ($author && ! in_array($author->getKey(), $told, true)) {
            $author->notify(new SiteNotification(
                NotificationKind::PostCommented,
                "{$actor->name} ".($comment->parent_id ? 'replied to a comment' : 'commented').' on your post “'.$post->title.'”',
                $excerpt,
                $url,
                ['post_id' => $post->getKey(), 'comment_id' => $comment->getKey()],
            ));
            $told[] = $author->getKey();
        }

        foreach ($this->admins($told) as $admin) {
            $admin->notify(new SiteNotification(
                NotificationKind::NewComment,
                "{$actor->name} ".($comment->parent_id ? 'replied' : 'commented').' on “'.$post->title.'”',
                $excerpt,
                $this->path('admin.comments.index', ['post' => $post->getKey()]),
                ['post_id' => $post->getKey(), 'comment_id' => $comment->getKey()],
            ));
        }
    }

    public function memberRegistered(User $member): void
    {
        foreach ($this->admins([$member->getKey()]) as $admin) {
            $admin->notify(new SiteNotification(
                NotificationKind::MemberRegistered,
                "New member: {$member->name}",
                $member->email,
                $this->path('admin.users.edit', $member),
                ['user_id' => $member->getKey()],
            ));
        }
    }

    /**
     * Tells admins when a post goes live or is scheduled. Call only when it was not published before this save.
     */
    public function postPublished(Post $post, User $actor): void
    {
        $post->loadMissing('user');
        $message = $post->isScheduled()
            ? "{$post->user->name} scheduled “{$post->title}” for {$post->published_at->format('j M Y, g:i a')}"
            : "{$post->user->name} published “{$post->title}”";

        foreach ($this->admins([$actor->getKey()]) as $admin) {
            $admin->notify(new SiteNotification(
                NotificationKind::PostPublished,
                $message,
                $post->summary ? Str::limit($post->summary, self::EXCERPT_LENGTH) : null,
                $this->path('posts.show', $post),
                ['post_id' => $post->getKey()],
            ));
        }
    }

    /**
     * Repeated admin edits before the author looks update one notification.
     */
    public function postEditedByAdmin(Post $post, User $admin): void
    {
        $author = $post->user;

        if ($author === null || $author->is($admin)) {
            return;
        }

        $this->sendOrReplace($author, new SiteNotification(
            NotificationKind::PostEditedByAdmin,
            "An admin edited your post “{$post->title}”",
            'See the highlighted changes.',
            $this->path('posts.changes', $post),
            ['post_id' => $post->getKey()],
        ), $this->unreadAbout($author, NotificationKind::PostEditedByAdmin, $post));
    }

    public function postUnpublishedByAdmin(Post $post, User $admin): void
    {
        $author = $post->user;

        if ($author === null || $author->is($admin)) {
            return;
        }

        $author->notify(new SiteNotification(
            NotificationKind::PostUnpublishedByAdmin,
            "An admin unpublished your post “{$post->title}”",
            'It is a draft again; only you can see it.',
            $this->path('posts.edit', $post),
            ['post_id' => $post->getKey()],
        ));
    }

    /**
     * Sent before the post is deleted; the notification links to the author's post list.
     */
    public function postDeletedByAdmin(Post $post, User $admin): void
    {
        $author = $post->user;

        if ($author === null || $author->is($admin)) {
            return;
        }

        $author->notify(new SiteNotification(
            NotificationKind::PostDeletedByAdmin,
            "An admin deleted your post “{$post->title}”",
            url: $this->path('posts.mine'),
        ));
    }

    /**
     * Only admins acting on someone else's comment; a post's author hiding comments on their own post is not announced.
     */
    public function commentHiddenByAdmin(Comment $comment, User $actor): void
    {
        $comment->loadMissing(['user', 'post']);

        if (! $actor->isAdmin() || $comment->user->is($actor)) {
            return;
        }

        $comment->user->notify(new SiteNotification(
            NotificationKind::CommentHiddenByAdmin,
            "An admin hid your comment on “{$comment->post->title}”",
            Str::limit($comment->body, self::EXCERPT_LENGTH),
            $this->path('posts.show', $comment->post).'#comment-'.$comment->getKey(),
            ['post_id' => $comment->post_id, 'comment_id' => $comment->getKey()],
        ));
    }

    public function commentDeletedByAdmin(Comment $comment, User $actor): void
    {
        $comment->loadMissing(['user', 'post']);

        if (! $actor->isAdmin() || $comment->user->is($actor)) {
            return;
        }

        $comment->user->notify(new SiteNotification(
            NotificationKind::CommentDeletedByAdmin,
            "An admin deleted your comment on “{$comment->post->title}”",
            Str::limit($comment->body, self::EXCERPT_LENGTH),
            $this->path('posts.show', $comment->post),
            ['post_id' => $comment->post_id],
        ));
    }

    /**
     * Active admins, leaving out the given user ids.
     *
     * @param  list<int>  $except
     * @return Collection<int, User>
     */
    private function admins(array $except): Collection
    {
        return User::query()
            ->where('role', UserRole::Admin)
            ->where('is_active', true)
            ->whereKeyNot($except)
            ->get();
    }

    private function unreadAbout(User $user, NotificationKind $kind, Post $post): ?DatabaseNotification
    {
        return $user->unreadNotifications()
            ->where('data->kind', $kind->value)
            ->where('data->post_id', $post->getKey())
            ->first();
    }

    /**
     * Rewrites an unread notification about the same thing (moving it to the top) or sends a new one.
     */
    private function sendOrReplace(User $user, SiteNotification $notification, ?DatabaseNotification $existing): void
    {
        if ($existing === null) {
            $user->notify($notification);

            return;
        }

        $existing->forceFill([
            'data' => $notification->toArray($user),
            'created_at' => now(),
        ])->save();
    }

    /**
     * Site-relative address, so links keep working if the domain changes.
     */
    private function path(string $route, mixed $parameters = []): string
    {
        return route($route, $parameters, absolute: false);
    }
}
