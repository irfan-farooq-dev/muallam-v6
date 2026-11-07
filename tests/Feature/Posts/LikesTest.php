<?php

namespace Tests\Feature\Posts;

use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LikesTest extends TestCase
{
    use RefreshDatabase;

    public function test_reader_can_like_a_post(): void
    {
        $reader = User::factory()->create();
        $post = Post::factory()->published()->create(['likes_count' => 4]);

        $this->actingAs($reader)->postJson(route('posts.like', $post))
            ->assertOk()
            ->assertExactJson(['liked' => true, 'likes_count' => 5]);

        $this->assertDatabaseHas('likes', ['user_id' => $reader->id, 'post_id' => $post->id]);
        $this->assertSame(5, $post->fresh()->likes_count);
    }

    public function test_liking_again_removes_the_like(): void
    {
        $reader = User::factory()->create();
        $post = Post::factory()->published()->create();

        $this->actingAs($reader)->postJson(route('posts.like', $post));
        $this->actingAs($reader)->postJson(route('posts.like', $post))
            ->assertExactJson(['liked' => false, 'likes_count' => 0]);

        $this->assertDatabaseCount('likes', 0);
        $this->assertSame(0, $post->fresh()->likes_count);
    }

    public function test_count_never_goes_below_zero(): void
    {
        $reader = User::factory()->create();
        $post = Post::factory()->published()->create(['likes_count' => 0]);
        Like::factory()->for($reader)->for($post)->create();

        $this->actingAs($reader)->postJson(route('posts.like', $post))
            ->assertExactJson(['liked' => false, 'likes_count' => 0]);
    }

    public function test_counts_from_different_readers_add_up(): void
    {
        $post = Post::factory()->published()->create();

        foreach (User::factory()->count(3)->create() as $reader) {
            $this->actingAs($reader)->postJson(route('posts.like', $post));
        }

        $this->assertSame(3, $post->fresh()->likes_count);
        $this->assertSame(3, $post->likes()->count());
    }

    public function test_liking_does_not_change_the_updated_time(): void
    {
        $post = Post::factory()->published()->create(['updated_at' => now()->subWeek()->startOfSecond()]);
        $before = $post->updated_at;

        $this->actingAs(User::factory()->create())->postJson(route('posts.like', $post));

        $this->assertTrue($post->fresh()->updated_at->equalTo($before));
    }

    public function test_authors_cannot_like_their_own_post(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->for($author)->published()->create();

        $this->actingAs($author)->postJson(route('posts.like', $post))->assertForbidden();
        $this->assertDatabaseCount('likes', 0);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function hiddenStates(): array
    {
        return ['draft' => ['draft'], 'scheduled' => ['scheduled'], 'private' => ['private'], 'restricted' => ['restricted']];
    }

    #[DataProvider('hiddenStates')]
    public function test_hidden_posts_cannot_be_liked(string $state): void
    {
        $post = Post::factory()->{$state}()->create();

        $this->actingAs(User::factory()->create())->postJson(route('posts.like', $post))->assertForbidden();
    }

    public function test_guests_cannot_like_and_are_sent_through_sign_in_back_to_the_post(): void
    {
        config(['auth.timebox_duration' => 0]);
        $post = Post::factory()->published()->create();
        User::factory()->create(['email' => 'reader@example.com', 'password' => 'secret-pass-1']);

        $this->postJson(route('posts.like', $post))->assertUnauthorized();

        $this->get(route('posts.like.sign-in', $post))->assertRedirect(route('login'));
        $this->post(route('login.store'), ['email' => 'reader@example.com', 'password' => 'secret-pass-1'])
            ->assertRedirect(route('posts.like.sign-in', $post));
        $this->get(route('posts.like.sign-in', $post))
            ->assertRedirect(route('posts.show', $post))
            ->assertSessionHas('info');
    }

    public function test_plain_form_submit_without_javascript_returns_to_the_post(): void
    {
        $post = Post::factory()->published()->create();

        $this->actingAs(User::factory()->create())->post(route('posts.like', $post))
            ->assertRedirect(route('posts.show', $post));

        $this->assertSame(1, $post->fresh()->likes_count);
    }

    public function test_post_page_shows_the_right_button_for_each_visitor(): void
    {
        $author = User::factory()->create();
        $reader = User::factory()->create();
        $post = Post::factory()->for($author)->published()->create(['likes_count' => 1]);
        Like::factory()->for($reader)->for($post)->create();

        $this->get(route('posts.show', $post))->assertSee(route('posts.like.sign-in', $post));
        $this->actingAs($reader)->get(route('posts.show', $post))
            ->assertSee('action="'.route('posts.like', $post).'"', false)
            ->assertSee('aria-pressed="true"', false)
            ->assertSee('Liked');
        $this->actingAs($author)->get(route('posts.show', $post))
            ->assertSee('Likes on your post')
            ->assertDontSee('action="'.route('posts.like', $post).'"', false);
    }

    public function test_liked_posts_page_lists_public_posts_most_recently_liked_first(): void
    {
        $reader = User::factory()->create();
        $first = Post::factory()->published()->create(['title' => 'Liked first']);
        $second = Post::factory()->published()->create(['title' => 'Liked second']);
        $hidden = Post::factory()->published()->create(['title' => 'Later made private']);
        Post::factory()->published()->create(['title' => 'Not liked']);

        Like::factory()->for($reader)->for($first)->create(['created_at' => now()->subDays(2)]);
        Like::factory()->for($reader)->for($second)->create(['created_at' => now()->subDay()]);
        Like::factory()->for($reader)->for($hidden)->create();
        $hidden->update(['status' => 'private', 'published_at' => null]);

        $this->actingAs($reader)->get(route('likes.index'))
            ->assertOk()
            ->assertSeeInOrder(['Liked second', 'Liked first'])
            ->assertDontSee('Later made private')
            ->assertDontSee('Not liked');
    }

    public function test_liked_posts_page_is_in_the_member_menu_and_needs_sign_in(): void
    {
        $this->get(route('likes.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertSee(route('likes.index'));
    }
}
