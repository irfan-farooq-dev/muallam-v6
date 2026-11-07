<?php

namespace Tests\Feature\Posts;

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostShare;
use App\Models\User;
use App\Notifications\RestrictedPostInvite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RestrictedSharingTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->author = User::factory()->create(['name' => 'Irfan Farooq', 'email' => 'irfan@example.com']);
        $this->post = Post::factory()->for($this->author)->restricted('open-sesame')->create(['title' => 'Family recipes']);
    }

    /**
     * Invites $email and returns the raw token from the email that was "sent".
     */
    private function inviteAndGetToken(string $email = 'friend@example.com'): string
    {
        $this->actingAs($this->author)->post(route('posts.shares.store', $this->post), ['emails' => $email]);

        $token = null;
        Notification::assertSentOnDemand(RestrictedPostInvite::class, function (RestrictedPostInvite $notification, array $channels, AnonymousNotifiable $notifiable) use ($email, &$token): bool {
            if ($notifiable->routes['mail'] === $email) {
                $token = $notification->token;
            }

            return true;
        });
        $this->post('/logout');

        return $token;
    }

    public function test_author_can_make_a_post_restricted_with_a_password(): void
    {
        $post = Post::factory()->for($this->author)->published()->create();

        $this->actingAs($this->author)->put(route('posts.update', $post), [
            'title' => $post->title,
            'category_id' => Category::factory()->create()->id,
            'body' => '<p>Text</p>',
            'action' => 'publish',
            'visibility' => 'restricted',
            'access_password' => 'family-only',
        ])->assertSessionHasNoErrors();

        $post->refresh();
        $this->assertSame(PostStatus::Restricted, $post->status);
        $this->assertTrue(Hash::check('family-only', $post->access_password));
        $this->assertArrayNotHasKey('access_password', $post->toArray());
    }

    public function test_leaving_the_password_empty_keeps_the_current_one(): void
    {
        $this->actingAs($this->author)->put(route('posts.update', $this->post), [
            'title' => 'Family recipes',
            'category_id' => Category::factory()->create()->id,
            'body' => '<p>Text</p>',
            'action' => 'publish',
            'visibility' => 'restricted',
            'access_password' => '',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('open-sesame', $this->post->fresh()->access_password));
    }

    public function test_sharing_panel_shows_on_restricted_posts_only(): void
    {
        $this->actingAs($this->author)->get(route('posts.edit', $this->post))->assertSee('Send invites');

        $public = Post::factory()->for($this->author)->published()->create();
        $this->actingAs($this->author)->get(route('posts.edit', $public))->assertDontSee('Send invites');
    }

    public function test_author_can_invite_several_people_at_once(): void
    {
        $this->actingAs($this->author)
            ->post(route('posts.shares.store', $this->post), ['emails' => "Friend@Example.com, aunt@example.com\nfriend@example.com"])
            ->assertRedirect(route('posts.edit', $this->post).'#sharing')
            ->assertSessionHas('success', '2 invites sent.');

        $this->assertSame(['aunt@example.com', 'friend@example.com'], $this->post->shares()->orderBy('email')->pluck('email')->all());
        Notification::assertSentOnDemandTimes(RestrictedPostInvite::class, 2);
    }

    public function test_invite_email_has_a_personal_link_and_no_password(): void
    {
        $this->actingAs($this->author)->post(route('posts.shares.store', $this->post), ['emails' => 'friend@example.com']);

        Notification::assertSentOnDemand(RestrictedPostInvite::class, function (RestrictedPostInvite $notification, array $channels, AnonymousNotifiable $notifiable): bool {
            $mail = $notification->toMail($notifiable);
            $text = implode(' ', [...$mail->introLines, ...$mail->outroLines]);

            return $notifiable->routes['mail'] === 'friend@example.com'
                && $mail->subject === 'Irfan Farooq shared a post with you on Muallam'
                && str_contains($mail->actionUrl, '/posts/'.$this->post->slug.'/access?token=')
                && ! str_contains($text, 'open-sesame');
        });

        $share = PostShare::sole();
        $this->assertSame(64, strlen($share->token_hash));
    }

    public function test_invalid_invites_are_rejected(): void
    {
        $send = fn (string $emails) => $this->actingAs($this->author)->post(route('posts.shares.store', $this->post), ['emails' => $emails]);

        $send('')->assertSessionHasErrorsIn('sharing', ['emails' => 'Enter at least one email address.']);
        $send('friend@example.com, not-an-email')->assertSessionHasErrorsIn('sharing', ['emails' => 'These are not valid email addresses: not-an-email']);
        $send('irfan@example.com')->assertSessionHasErrorsIn('sharing', ['emails' => 'You do not need to invite yourself.']);
        $send(implode(',', array_map(fn (int $i): string => "p{$i}@example.com", range(1, 21))))
            ->assertSessionHasErrorsIn('sharing', ['emails' => 'You can invite up to 20 people at a time.']);

        $this->assertDatabaseCount('post_shares', 0);
    }

    public function test_only_the_author_can_manage_sharing(): void
    {
        $share = PostShare::factory()->for($this->post)->create();
        $other = User::factory()->create();

        $this->actingAs($other)->post(route('posts.shares.store', $this->post), ['emails' => 'x@example.com'])->assertForbidden();
        $this->actingAs($other)->delete(route('posts.shares.destroy', [$this->post, $share]))->assertForbidden();
        $this->actingAs($other)->post(route('posts.shares.resend', [$this->post, $share]))->assertForbidden();
    }

    public function test_invited_reader_opens_the_link_enters_the_password_and_reads_the_post(): void
    {
        $token = $this->inviteAndGetToken();

        $this->get(route('posts.access', ['post' => $this->post, 'token' => $token]))
            ->assertRedirect(route('posts.access', $this->post));

        $this->get(route('posts.access', $this->post))
            ->assertOk()
            ->assertSee('Family recipes')
            ->assertSee('Irfan Farooq shared this post with you.');

        $this->post(route('posts.access.unlock', $this->post), ['password' => 'open-sesame'])
            ->assertRedirect(route('posts.show', $this->post));

        $this->get(route('posts.show', $this->post))
            ->assertOk()
            ->assertSee('Shared with you privately')
            ->assertSee('noindex', false);

        $this->assertNotNull(PostShare::sole()->last_accessed_at);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $token = $this->inviteAndGetToken();
        $this->get(route('posts.access', ['post' => $this->post, 'token' => $token]));

        $this->post(route('posts.access.unlock', $this->post), ['password' => 'guess'])
            ->assertSessionHasErrorsIn('access', ['password' => 'That password is not right. Ask Irfan Farooq for it.']);

        $this->get(route('posts.show', $this->post))->assertNotFound();
    }

    public function test_password_alone_without_an_invite_link_does_not_work(): void
    {
        $this->get(route('posts.access', $this->post))->assertNotFound();
        $this->post(route('posts.access.unlock', $this->post), ['password' => 'open-sesame'])->assertNotFound();
        $this->get(route('posts.show', $this->post))->assertNotFound();
    }

    public function test_made_up_or_other_post_tokens_are_rejected(): void
    {
        $token = $this->inviteAndGetToken();
        $otherPost = Post::factory()->for($this->author)->restricted()->create();

        $this->get(route('posts.access', ['post' => $this->post, 'token' => 'made-up']))->assertNotFound();
        $this->get(route('posts.access', ['post' => $otherPost, 'token' => $token]))->assertNotFound();
    }

    public function test_password_guessing_is_limited(): void
    {
        $token = $this->inviteAndGetToken();
        $this->get(route('posts.access', ['post' => $this->post, 'token' => $token]));

        foreach (range(1, 5) as $attempt) {
            $this->post(route('posts.access.unlock', $this->post), ['password' => "guess-{$attempt}"]);
        }

        $this->post(route('posts.access.unlock', $this->post), ['password' => 'open-sesame'])->assertTooManyRequests();
    }

    public function test_revoking_ends_access_immediately(): void
    {
        $token = $this->inviteAndGetToken();
        $this->get(route('posts.access', ['post' => $this->post, 'token' => $token]));
        $this->post(route('posts.access.unlock', $this->post), ['password' => 'open-sesame']);
        $this->get(route('posts.show', $this->post))->assertOk();

        $readerSession = $this->app['session']->all();
        $this->actingAs($this->author)->delete(route('posts.shares.destroy', [$this->post, PostShare::sole()]))
            ->assertSessionHas('success', 'friend@example.com can no longer open this post.');
        $this->post('/logout');

        $this->withSession($readerSession)->get(route('posts.show', $this->post))->assertNotFound();
        $this->get(route('posts.access', ['post' => $this->post, 'token' => $token]))->assertNotFound();
    }

    public function test_resend_sends_a_new_link_and_the_old_one_stops_working(): void
    {
        $oldToken = $this->inviteAndGetToken();
        $share = PostShare::sole();

        $this->actingAs($this->author)->post(route('posts.shares.resend', [$this->post, $share]))
            ->assertSessionHas('success', 'A new link was sent to friend@example.com. The old link no longer works.');
        $this->post('/logout');

        Notification::assertSentOnDemandTimes(RestrictedPostInvite::class, 2);
        $this->get(route('posts.access', ['post' => $this->post, 'token' => $oldToken]))->assertNotFound();
    }

    public function test_access_page_for_a_post_that_is_no_longer_restricted_goes_to_the_post(): void
    {
        $public = Post::factory()->published()->create();

        $this->get(route('posts.access', $public))->assertRedirect(route('posts.show', $public));
    }

    public function test_author_sees_manage_sharing_on_their_restricted_post(): void
    {
        $this->actingAs($this->author)->get(route('posts.show', $this->post))
            ->assertSee('Manage sharing')
            ->assertSee(route('posts.edit', $this->post).'#sharing');
    }
}
