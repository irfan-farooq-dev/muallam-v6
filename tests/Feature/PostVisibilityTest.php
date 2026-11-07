<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\Post;
use Database\Factories\PostFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PostVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_post_with_past_publish_time_is_publicly_visible(): void
    {
        $post = Post::factory()->published()->create();

        $this->assertTrue(Post::publiclyVisible()->whereKey($post->id)->exists());
    }

    /**
     * @return array<string, array{callable(PostFactory): PostFactory}>
     */
    public static function hiddenPostStates(): array
    {
        return [
            'draft' => [fn (PostFactory $factory) => $factory->draft()],
            'scheduled' => [fn (PostFactory $factory) => $factory->scheduled()],
            'private' => [fn (PostFactory $factory) => $factory->private()],
            'restricted' => [fn (PostFactory $factory) => $factory->restricted()],
            'published without publish time' => [fn (PostFactory $factory) => $factory->state([
                'status' => PostStatus::Published,
                'published_at' => null,
            ])],
        ];
    }

    #[DataProvider('hiddenPostStates')]
    public function test_post_is_not_publicly_visible(callable $applyState): void
    {
        $post = $applyState(Post::factory())->create();

        $this->assertFalse(Post::publiclyVisible()->whereKey($post->id)->exists());
    }

    public function test_scheduled_post_becomes_visible_once_its_publish_time_passes(): void
    {
        $post = Post::factory()->scheduled()->create(['published_at' => now()->addHour()]);

        $this->assertTrue($post->isScheduled());

        $this->travel(2)->hours();

        $this->assertFalse($post->fresh()->isScheduled());
        $this->assertTrue(Post::publiclyVisible()->whereKey($post->id)->exists());
    }

    public function test_new_post_defaults_to_draft_with_comments_allowed(): void
    {
        $post = new Post;

        $this->assertSame(PostStatus::Draft, $post->status);
        $this->assertTrue($post->allow_comments);
    }

    public function test_access_password_is_hashed_and_hidden_from_serialization(): void
    {
        $post = Post::factory()->restricted('open-sesame')->create();

        $this->assertTrue(Hash::check('open-sesame', $post->access_password));
        $this->assertArrayNotHasKey('access_password', $post->toArray());
    }
}
