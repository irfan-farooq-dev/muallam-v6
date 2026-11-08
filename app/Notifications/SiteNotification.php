<?php

namespace App\Notifications;

use App\Enums\NotificationKind;
use Illuminate\Notifications\Notification;

/**
 * One in-site notification (the bell). The wording is decided by App\Services\Notifier;
 * this class only stores it. Not queued: cPanel hosting has no queue worker.
 */
class SiteNotification extends Notification
{
    /**
     * @param  string  $message  One line, e.g. "Ali commented on your post “My garden”".
     * @param  string|null  $excerpt  Optional second line (e.g. the comment), plain text.
     * @param  string|null  $url  Site-relative address the notification opens, e.g. "/posts/my-garden#comment-4".
     * @param  array<string, mixed>  $extra  Values used for grouping (post_id, actor_ids, ...).
     */
    public function __construct(
        public NotificationKind $kind,
        public string $message,
        public ?string $excerpt = null,
        public ?string $url = null,
        public array $extra = [],
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind->value,
            'message' => $this->message,
            'excerpt' => $this->excerpt,
            'url' => $this->url,
            ...$this->extra,
        ];
    }
}
