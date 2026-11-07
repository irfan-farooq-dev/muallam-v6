<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;

/**
 * Keeps the author's version of a post when an admin changes its words, so the author can see what changed.
 * Readers never see any of this: the post itself always holds plain, current text.
 */
class AdminEditTracker
{
    /**
     * Fields whose changes are shown to the author.
     */
    public const TRACKED = ['title', 'summary', 'body'];

    /**
     * Call before filling the post with the new input.
     *
     * @return array{title: string, summary: ?string, body: string}
     */
    public function snapshot(Post $post): array
    {
        return [
            'title' => (string) $post->getOriginal('title'),
            'summary' => $post->getOriginal('summary'),
            'body' => (string) $post->getOriginal('body'),
        ];
    }

    /**
     * Call after the post is saved.
     * An admin changing someone else's words opens a record (keeping the author's version from before the
     * first unreviewed change). The author saving the post themselves counts as having reviewed it.
     *
     * @param  array{title: string, summary: ?string, body: string}  $before
     */
    public function recordSave(Post $post, User $editor, array $before): void
    {
        if ($post->user_id === $editor->getKey()) {
            $this->resolve($post);

            return;
        }

        $wordsChanged = $before['title'] !== (string) $post->title
            || (string) $before['summary'] !== (string) $post->summary
            || $before['body'] !== (string) $post->body;

        if (! $wordsChanged) {
            return;
        }

        $open = $post->pendingAdminEdit()->first();

        if ($open) {
            $open->editor()->associate($editor)->touch();

            return;
        }

        $post->adminEdits()->make([
            'previous_title' => $before['title'],
            'previous_summary' => $before['summary'],
            'previous_body' => $before['body'],
        ])->editor()->associate($editor)->save();
    }

    public function resolve(Post $post): void
    {
        $post->adminEdits()->whereNull('resolved_at')->update(['resolved_at' => now()]);
    }
}
