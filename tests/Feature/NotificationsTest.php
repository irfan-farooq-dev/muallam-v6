<?php

namespace Tests\Feature;

use App\Enums\NotificationKind;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Notifications\SiteNotification;
use App\Services\PostComments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private User $admin;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->author = User::factory()->create(['name' => 'Amina Khan']);
        $this->admin = User::factory()->admin()->create(['name' => 'Site Admin']);
        $this->post = Post::factory()->published()->for($this->author)->create(['title' => 'My garden', 'slug' => 'my-garden']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dataFor(User $user, ?NotificationKind $kind = null): array
    {
        return $user->notifications()->get()
            ->map(fn (DatabaseNotification $notification): array => $notification->data)
            ->filter(fn (array $data): bool => $kind === null || $data['kind'] === $kind->value)
            ->values()
            ->all();
    }

    private function comment(User $writer, string $body = 'Lovely post', ?Comment $parent = null): Comment
    {
        $this->actingAs($writer)->post(route('comments.store', $this->post), array_filter(['body' => $body, 'parent_id' => $parent?->id]));

        return Comment::query()->latest('id')->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function postInput(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Fresh post',
            'summary' => 'A short summary.',
            'category_id' => Category::factory()->create()->id,
            'body' => '<p>Hello world.</p>',
            'action' => 'publish',
            'visibility' => 'public',
            'allow_comments' => '1',
        ], $overrides);
    }

    public function test_likes_are_grouped_into_one_unread_notification(): void
    {
        $ali = User::factory()->create(['name' => 'Ali']);
        $sara = User::factory()->create(['name' => 'Sara']);
        $omar = User::factory()->create(['name' => 'Omar']);

        $this->actingAs($ali)->postJson(route('posts.like', $this->post));
        $this->assertSame('Ali liked your post “My garden”', $this->dataFor($this->author)[0]['message']);

        $this->actingAs($sara)->postJson(route('posts.like', $this->post));
        $this->actingAs($omar)->postJson(route('posts.like', $this->post));
        // Unliking and liking again does not count Ali twice.
        $this->actingAs($ali)->postJson(route('posts.like', $this->post));
        $this->actingAs($ali)->postJson(route('posts.like', $this->post));

        $likes = $this->dataFor($this->author, NotificationKind::PostLiked);
        $this->assertCount(1, $likes);
        $this->assertSame('Ali and 2 others liked your post “My garden”', $likes[0]['message']);
        $this->assertSame('/posts/my-garden', $likes[0]['url']);
    }

    public function test_a_like_after_the_author_read_the_last_one_starts_a_new_notification(): void
    {
        $this->actingAs(User::factory()->create())->postJson(route('posts.like', $this->post));
        $this->author->unreadNotifications->markAsRead();

        $this->actingAs(User::factory()->create(['name' => 'Bilal']))->postJson(route('posts.like', $this->post));

        $this->assertCount(2, $this->dataFor($this->author, NotificationKind::PostLiked));
        $this->assertSame(1, $this->author->unreadNotifications()->count());
    }

    public function test_a_comment_tells_the_author_and_the_admins_but_not_the_writer(): void
    {
        $reader = User::factory()->create(['name' => 'Ali']);
        $comment = $this->comment($reader, 'Lovely post');

        $authorNote = $this->dataFor($this->author)[0];
        $this->assertSame(NotificationKind::PostCommented->value, $authorNote['kind']);
        $this->assertSame('Ali commented on your post “My garden”', $authorNote['message']);
        $this->assertSame('Lovely post', $authorNote['excerpt']);
        $this->assertSame('/posts/my-garden#comment-'.$comment->id, $authorNote['url']);

        $adminNote = $this->dataFor($this->admin)[0];
        $this->assertSame(NotificationKind::NewComment->value, $adminNote['kind']);
        $this->assertSame('/admin/comments?post='.$this->post->id, $adminNote['url']);

        $this->assertSame([], $this->dataFor($reader));
    }

    public function test_a_reply_tells_the_replied_to_writer_once_even_when_they_are_the_author(): void
    {
        $ali = User::factory()->create(['name' => 'Ali']);
        $sara = User::factory()->create(['name' => 'Sara']);
        $first = $this->comment($ali, 'First!');
        $this->author->notifications()->delete();
        $this->admin->notifications()->delete();

        $this->comment($sara, 'Agreed', $first);

        $this->assertSame('Sara replied to your comment on “My garden”', $this->dataFor($ali, NotificationKind::CommentReplied)[0]['message']);
        $this->assertSame('Sara replied to a comment on your post “My garden”', $this->dataFor($this->author)[0]['message']);
        $this->assertCount(1, $this->dataFor($this->admin, NotificationKind::NewComment));

        // The author replying to Ali: Ali hears about it; the author is not told about their own reply.
        $this->author->notifications()->delete();
        $this->comment($this->author, 'Thanks Ali', $first);
        $this->assertCount(2, $this->dataFor($ali, NotificationKind::CommentReplied));
        $this->assertSame([], $this->dataFor($this->author));
    }

    public function test_an_admin_who_wrote_the_post_gets_one_notification_per_comment(): void
    {
        $this->post->user()->associate($this->admin)->save();

        $this->comment(User::factory()->create());

        $this->assertSame([NotificationKind::PostCommented->value], array_column($this->dataFor($this->admin), 'kind'));
    }

    public function test_admins_hear_about_new_members(): void
    {
        $this->post(route('register.store'), [
            'name' => 'New Person',
            'email' => 'new@example.com',
            'password' => 'Strong-Pass-123',
            'password_confirmation' => 'Strong-Pass-123',
        ]);

        $note = $this->dataFor($this->admin, NotificationKind::MemberRegistered)[0];
        $this->assertSame('New member: New Person', $note['message']);
        $this->assertSame('new@example.com', $note['excerpt']);
    }

    public function test_admins_hear_when_a_post_is_published_or_scheduled_but_not_on_later_edits(): void
    {
        $this->actingAs($this->author)->post(route('posts.store'), $this->postInput(['title' => 'Fresh post']));
        $this->assertSame('Amina Khan published “Fresh post”', $this->dataFor($this->admin, NotificationKind::PostPublished)[0]['message']);

        $fresh = Post::where('title', 'Fresh post')->sole();
        $this->actingAs($this->author)->put(route('posts.update', $fresh), $this->postInput(['title' => 'Fresh post']));
        $this->assertCount(1, $this->dataFor($this->admin, NotificationKind::PostPublished));

        $this->actingAs($this->author)->post(route('posts.store'), $this->postInput(['title' => 'Draft only', 'action' => 'draft']));
        $this->assertCount(1, $this->dataFor($this->admin, NotificationKind::PostPublished));

        $this->actingAs($this->author)->post(route('posts.store'), $this->postInput([
            'title' => 'Later post', 'schedule' => '1', 'scheduled_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
        ]));
        $messages = array_column($this->dataFor($this->admin, NotificationKind::PostPublished), 'message');
        $this->assertCount(2, $messages);
        $this->assertNotEmpty(array_filter($messages, fn (string $message): bool => str_starts_with($message, 'Amina Khan scheduled “Later post” for')));
    }

    public function test_admin_publishing_does_not_notify_themselves(): void
    {
        $this->actingAs($this->admin)->post(route('posts.store'), $this->postInput());

        $this->assertSame([], $this->dataFor($this->admin));
    }

    public function test_author_hears_about_admin_edits_grouped_with_a_link_to_the_changes(): void
    {
        $input = ['title' => 'My garden', 'body' => '<p>Changed.</p>', 'category_id' => Category::factory()->create()->id];
        $this->actingAs($this->admin)->put(route('posts.update', $this->post), $this->postInput($input));
        $this->actingAs($this->admin)->put(route('posts.update', $this->post), $this->postInput([...$input, 'body' => '<p>Changed again.</p>']));

        $edits = $this->dataFor($this->author, NotificationKind::PostEditedByAdmin);
        $this->assertCount(1, $edits);
        $this->assertSame('An admin edited your post “My garden”', $edits[0]['message']);
        $this->assertSame('/posts/my-garden/changes', $edits[0]['url']);
    }

    public function test_author_hears_when_an_admin_unpublishes_or_deletes_their_post(): void
    {
        $this->actingAs($this->admin)->post(route('admin.posts.unpublish', $this->post));
        $this->assertSame('/posts/my-garden/edit', $this->dataFor($this->author, NotificationKind::PostUnpublishedByAdmin)[0]['url']);

        $this->actingAs($this->admin)->delete(route('admin.posts.destroy', $this->post));
        $deleted = $this->dataFor($this->author, NotificationKind::PostDeletedByAdmin)[0];
        $this->assertSame('An admin deleted your post “My garden”', $deleted['message']);
        $this->assertSame('/my/posts', $deleted['url']);
    }

    public function test_admin_unpublishing_from_the_editor_tells_the_author(): void
    {
        $this->actingAs($this->admin)->put(route('posts.update', $this->post), $this->postInput(['title' => 'My garden', 'action' => 'draft']));

        $this->assertCount(1, $this->dataFor($this->author, NotificationKind::PostUnpublishedByAdmin));
    }

    public function test_authors_managing_their_own_posts_are_not_notified(): void
    {
        $this->actingAs($this->author)->put(route('posts.update', $this->post), $this->postInput(['title' => 'Renamed', 'action' => 'draft']));
        $this->actingAs($this->author)->delete(route('posts.destroy', $this->post));

        $this->assertSame([], $this->dataFor($this->author));
    }

    public function test_comment_writer_hears_when_an_admin_hides_or_deletes_their_comment(): void
    {
        $writer = User::factory()->create();
        $first = app(PostComments::class)->add($writer, $this->post, 'Off topic');
        $second = app(PostComments::class)->add($writer, $this->post, 'Spam link');

        $this->actingAs($this->admin)->patch(route('admin.comments.visibility', $first), ['visible' => '0']);
        $this->actingAs($this->admin)->delete(route('admin.comments.destroy', $second));

        $this->assertSame('An admin hid your comment on “My garden”', $this->dataFor($writer, NotificationKind::CommentHiddenByAdmin)[0]['message']);
        $this->assertSame('Spam link', $this->dataFor($writer, NotificationKind::CommentDeletedByAdmin)[0]['excerpt']);

        // Showing it again is not announced.
        $this->actingAs($this->admin)->patch(route('admin.comments.visibility', $first), ['visible' => '1']);
        $this->assertCount(2, $this->dataFor($writer));
    }

    public function test_bulk_hiding_tells_each_writer(): void
    {
        $writers = User::factory()->count(2)->create();
        $ids = $writers->map(fn (User $writer): int => app(PostComments::class)->add($writer, $this->post, 'Hello')->id)->all();

        $this->actingAs($this->admin)->post(route('admin.comments.bulk'), ['action' => 'hide', 'comments' => $ids]);

        $writers->each(fn (User $writer) => $this->assertCount(1, $this->dataFor($writer, NotificationKind::CommentHiddenByAdmin)));
    }

    public function test_post_author_hiding_a_comment_is_not_announced(): void
    {
        $writer = User::factory()->create();
        $comment = app(PostComments::class)->add($writer, $this->post, 'Hmm');

        $this->actingAs($this->author)->patch(route('comments.visibility', $comment), ['visible' => '0']);

        $this->assertSame([], $this->dataFor($writer));
    }

    public function test_feed_returns_own_unread_count_and_newest_items(): void
    {
        $this->comment(User::factory()->create(['name' => 'Ali']), 'Nice');
        User::factory()->create()->notify(new SiteNotification(NotificationKind::PostLiked, 'Someone else’s'));

        $this->actingAs($this->author)->getJson(route('notifications.feed'))
            ->assertOk()
            ->assertJsonPath('unread', 1)
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.message', 'Ali commented on your post “My garden”')
            ->assertJsonPath('items.0.icon', 'bi-chat')
            ->assertJsonPath('items.0.read', false);
    }

    public function test_opening_a_notification_marks_it_read_and_follows_its_link(): void
    {
        $comment = $this->comment(User::factory()->create());
        $notification = $this->author->notifications()->sole();

        $this->actingAs($this->author)->get(route('notifications.open', $notification->id))
            ->assertRedirect('/posts/my-garden#comment-'.$comment->id);
        $this->assertNotNull($notification->fresh()->read_at);

        $this->actingAs(User::factory()->create())->get(route('notifications.open', $notification->id))->assertNotFound();
    }

    public function test_links_never_lead_off_site(): void
    {
        $this->author->notify(new SiteNotification(NotificationKind::PostLiked, 'Odd', url: '//evil.example/x'));

        $this->actingAs($this->author)->get(route('notifications.open', $this->author->notifications()->sole()->id))
            ->assertRedirect(route('notifications.index'));
    }

    public function test_mark_all_as_read_and_the_pages(): void
    {
        $this->comment(User::factory()->create(['name' => 'Ali']), 'Nice');
        $this->comment(User::factory()->create(['name' => 'Sara']), 'Great');

        $this->actingAs($this->author)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('2 unread notifications.')
            ->assertSee('Sara commented on your post “My garden”')
            ->assertSee('Mark all as read');

        $this->actingAs($this->author)->from(route('notifications.index'))->post(route('notifications.read-all'))
            ->assertRedirect(route('notifications.index'));
        $this->assertSame(0, $this->author->unreadNotifications()->count());

        $this->actingAs($this->admin)->get(route('admin.notifications.index'))->assertOk()->assertSee('Ali commented on “My garden”');
        $this->actingAs($this->author)->get(route('admin.notifications.index'))->assertForbidden();
    }

    public function test_bell_shows_the_unread_count_in_both_layouts(): void
    {
        $this->comment(User::factory()->create());

        $this->actingAs($this->author)->get(route('home'))
            ->assertSee('notificationBell(', false)
            ->assertSee('<span class="notification-count" x-show="unread > 0" x-text="unread > 99 ? \'99+\' : unread" >1</span>', false);
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertSee('notification-bell-admin', false);

        $this->post(route('logout'));
        $this->get(route('home'))->assertDontSee('notificationBell(', false);
        $this->getJson(route('notifications.feed'))->assertUnauthorized();
    }

    public function test_old_read_notifications_are_pruned_and_deleted_users_lose_theirs(): void
    {
        $this->comment(User::factory()->create());
        $this->comment(User::factory()->create());
        $old = $this->author->notifications()->first();
        $old->forceFill(['read_at' => now()->subDays(91)])->save();

        $this->artisan('notifications:prune')->expectsOutputToContain('Deleted 1 old notification(s).');
        $this->assertSame(1, $this->author->notifications()->count());

        $this->actingAs($this->admin)->delete(route('admin.users.destroy', $this->author), ['confirm_email' => $this->author->email]);
        $this->assertSame(0, DatabaseNotification::query()->where('notifiable_id', $this->author->id)->count());
    }
}
