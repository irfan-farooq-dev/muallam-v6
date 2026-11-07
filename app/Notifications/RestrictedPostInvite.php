<?php

namespace App\Notifications;

use App\Models\Post;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emailed to someone invited to read a restricted post. The password is never included.
 */
class RestrictedPostInvite extends Notification
{
    public function __construct(public Post $post, public string $token) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $author = $this->post->user->name;

        return (new MailMessage)
            ->subject("{$author} shared a post with you on Muallam")
            ->greeting('Hello,')
            ->line("{$author} invited you to read \"{$this->post->title}\". It is not public; only invited people can open it.")
            ->action('Open the post', $this->url())
            ->line("You will be asked for the post's password. If you don't have it, ask {$author}.")
            ->line('This link is personal. Please do not forward it.');
    }

    public function url(): string
    {
        return route('posts.access', ['post' => $this->post, 'token' => $this->token]);
    }
}
