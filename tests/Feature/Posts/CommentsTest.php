<?php

namespace Tests\Feature\Posts;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CommentsTest extends TestCase
{
    use RefreshDatabase;

    private function livePost(array $attributes = []): Post
    {
        return Post::factory()->published()->create($attributes);
    }

    public function test_reader_can_comment_and_the_count_goes_up(): void
    {
        $reader = User::factory()->create();
        $post = $this->livePost(['comments_count' => 2]);

        $response = $this->actingAs($reader)->post(route('comments.store', $post), ['body' => "Great post!\nThanks."]);

        $comment = Comment::sole();
        $response->assertRedirect(route('posts.show', $post).'#comment-'.$comment->id)->assertSessionHas('success', 'Comment posted.');
        $this->assertTrue($comment->user->is($reader));
        $this->assertSame("Great post!\nThanks.", $comment->body);
        $this->assertSame(3, $post->fresh()->comments_count);
    }

    public function test_comment_text_is_shown_safely_with_line_breaks(): void
    {
        $post = $this->livePost();
        Comment::factory()->for($post)->create(['body' => "Line one\n<script>alert(1)</script>"]);

        $this->get(route('posts.show', $post))
            ->assertSee('Line one<br />', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidBodies(): array
    {
        return [
            'empty' => ['', 'Write a comment before posting.'],
            'only spaces' => ['    ', 'Write a comment before posting.'],
            'one letter' => ['k', 'The comment is too short.'],
            'too long' => [str_repeat('a', 2001), 'Comments can be up to 2000 characters.'],
        ];
    }

    #[DataProvider('invalidBodies')]
    public function test_invalid_comment_is_rejected(string $body, string $message): void
    {
        $post = $this->livePost();

        $this->actingAs(User::factory()->create())->post(route('comments.store', $post), ['body' => $body])
            ->assertSessionHasErrorsIn('comment', ['body' => $message]);

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_guests_and_unverified_users_cannot_comment(): void
    {
        $post = $this->livePost();

        $this->post(route('comments.store', $post), ['body' => 'Hello'])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->unverified()->create())
            ->post(route('comments.store', $post), ['body' => 'Hello'])
            ->assertRedirect(route('verification.notice'));

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_guest_sign_in_link_returns_to_the_comments(): void
    {
        $post = $this->livePost();

        $this->get(route('posts.show', $post))->assertSee(route('comments.sign-in', $post));
        $this->actingAs(User::factory()->create())->get(route('comments.sign-in', $post))
            ->assertRedirect(route('posts.show', $post).'#comments');
    }

    public function test_cannot_comment_when_comments_are_turned_off(): void
    {
        $post = $this->livePost(['allow_comments' => false]);

        $this->actingAs(User::factory()->create())->post(route('comments.store', $post), ['body' => 'Hello'])->assertForbidden();
        $this->get(route('posts.show', $post))->assertSee('Comments are turned off for this post.');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function hiddenStates(): array
    {
        return ['draft' => ['draft'], 'scheduled' => ['scheduled'], 'private' => ['private']];
    }

    #[DataProvider('hiddenStates')]
    public function test_cannot_comment_on_posts_that_are_not_public(string $state): void
    {
        $post = Post::factory()->{$state}()->create();

        $this->actingAs($post->user)->post(route('comments.store', $post), ['body' => 'Hello'])->assertForbidden();
    }

    public function test_reader_can_reply_to_a_comment(): void
    {
        $post = $this->livePost();
        $parent = Comment::factory()->for($post)->create();

        $this->actingAs(User::factory()->create())
            ->post(route('comments.store', $post), ['body' => 'I agree', 'parent_id' => $parent->id])
            ->assertSessionHas('success', 'Reply posted.');

        $this->assertSame($parent->id, Comment::where('body', 'I agree')->value('parent_id'));
        $this->assertSame(1, $post->fresh()->comments_count);
    }

    public function test_replies_only_go_one_level_deep(): void
    {
        $post = $this->livePost();
        $parent = Comment::factory()->for($post)->create();
        $reply = Comment::factory()->replyTo($parent)->create();

        $this->actingAs(User::factory()->create())
            ->post(route('comments.store', $post), ['body' => 'Deeper', 'parent_id' => $reply->id])
            ->assertForbidden();
    }

    public function test_cannot_reply_to_a_comment_on_another_post(): void
    {
        $post = $this->livePost();
        $elsewhere = Comment::factory()->for($this->livePost())->create();

        $this->actingAs(User::factory()->create())
            ->post(route('comments.store', $post), ['body' => 'Sneaky', 'parent_id' => $elsewhere->id])
            ->assertNotFound();
    }

    public function test_writer_can_delete_their_comment_and_the_count_goes_down(): void
    {
        $post = $this->livePost(['comments_count' => 1]);
        $comment = Comment::factory()->for($post)->create();

        $this->actingAs($comment->user)->delete(route('comments.destroy', $comment))
            ->assertRedirect(route('posts.show', $post).'#comments');

        $this->assertSoftDeleted($comment);
        $this->assertSame(0, $post->fresh()->comments_count);
    }

    public function test_other_readers_cannot_delete_a_comment_but_admins_can(): void
    {
        $comment = Comment::factory()->for($this->livePost())->create();

        $this->actingAs(User::factory()->create())->delete(route('comments.destroy', $comment))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->delete(route('comments.destroy', $comment))->assertRedirect();

        $this->assertSoftDeleted($comment);
    }

    public function test_deleted_comment_with_replies_stays_as_a_placeholder(): void
    {
        $post = $this->livePost();
        $parent = Comment::factory()->for($post)->create(['body' => 'Original thought']);
        Comment::factory()->replyTo($parent)->create(['body' => 'A reply']);
        $lonely = Comment::factory()->for($post)->create(['body' => 'Lonely comment']);
        $parent->delete();
        $lonely->delete();

        $this->get(route('posts.show', $post))
            ->assertSee('This comment was removed.')
            ->assertSee('A reply')
            ->assertDontSee('Original thought')
            ->assertDontSee('Lonely comment');
    }

    public function test_post_author_can_hide_and_show_comments(): void
    {
        $post = $this->livePost(['comments_count' => 1]);
        $comment = Comment::factory()->for($post)->create(['body' => 'Rude remark']);

        $this->actingAs($post->user)->patch(route('comments.visibility', $comment), ['visible' => 0])
            ->assertSessionHas('success', 'Comment hidden. Only you and admins can see it now.');
        $this->assertFalse($comment->fresh()->is_approved);
        $this->assertSame(0, $post->fresh()->comments_count);

        $this->actingAs(User::factory()->create())->get(route('posts.show', $post))->assertDontSee('Rude remark');
        $this->actingAs($post->user)->get(route('posts.show', $post))->assertSee('Rude remark')->assertSee('Hidden');

        $this->actingAs($post->user)->patch(route('comments.visibility', $comment), ['visible' => 1]);
        $this->assertTrue($comment->fresh()->is_approved);
        $this->assertSame(1, $post->fresh()->comments_count);
    }

    public function test_hiding_twice_does_not_change_the_count_twice(): void
    {
        $post = $this->livePost(['comments_count' => 1]);
        $comment = Comment::factory()->for($post)->create();

        $this->actingAs($post->user)->patch(route('comments.visibility', $comment), ['visible' => 0]);
        $this->actingAs($post->user)->patch(route('comments.visibility', $comment), ['visible' => 0]);

        $this->assertSame(0, $post->fresh()->comments_count);
    }

    public function test_only_the_post_author_or_an_admin_can_hide_comments(): void
    {
        $comment = Comment::factory()->for($this->livePost())->create();

        $this->actingAs($comment->user)->patch(route('comments.visibility', $comment), ['visible' => 0])->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->patch(route('comments.visibility', $comment), ['visible' => 0])->assertRedirect();
    }

    public function test_thread_shows_comments_oldest_first_with_replies_and_author_badge(): void
    {
        $post = $this->livePost();
        $first = Comment::factory()->for($post)->create(['body' => 'First comment', 'created_at' => now()->subHours(3)]);
        Comment::factory()->for($post)->create(['body' => 'Second comment', 'created_at' => now()->subHour()]);
        Comment::factory()->replyTo($first)->for($post->user)->create(['body' => 'Reply from the author', 'created_at' => now()->subHours(2)]);

        $this->get(route('posts.show', $post))
            ->assertSeeInOrder(['First comment', 'Reply from the author', 'Second comment'])
            ->assertSee('Author');
    }

    public function test_reply_box_is_offered_only_on_top_level_comments(): void
    {
        $post = $this->livePost();
        $parent = Comment::factory()->for($post)->create();
        $reply = Comment::factory()->replyTo($parent)->create();

        $this->actingAs(User::factory()->create())->get(route('posts.show', $post))
            ->assertSee('name="parent_id" value="'.$parent->id.'"', false)
            ->assertDontSee('name="parent_id" value="'.$reply->id.'"', false);
    }
}
