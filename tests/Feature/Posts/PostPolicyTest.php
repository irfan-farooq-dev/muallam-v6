<?php

namespace Tests\Feature\Posts;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PostPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_can_view_a_live_published_post(): void
    {
        $post = Post::factory()->published()->create();

        $this->assertTrue(Gate::forUser(null)->allows('view', $post));
        $this->assertTrue(Gate::forUser(User::factory()->create())->allows('view', $post));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function hiddenStates(): array
    {
        return ['draft' => ['draft'], 'scheduled' => ['scheduled'], 'private' => ['private'], 'restricted' => ['restricted']];
    }

    #[DataProvider('hiddenStates')]
    public function test_hidden_posts_are_only_visible_to_their_author_and_admins(string $state): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->for($author)->{$state}()->create();

        $this->assertFalse(Gate::forUser(null)->allows('view', $post));
        $this->assertFalse(Gate::forUser(User::factory()->create())->allows('view', $post));
        $this->assertTrue(Gate::forUser($author)->allows('view', $post));
        $this->assertTrue(Gate::forUser(User::factory()->admin()->create())->allows('view', $post));
    }

    public function test_only_verified_users_can_create_posts(): void
    {
        $this->assertTrue(Gate::forUser(User::factory()->create())->allows('create', Post::class));
        $this->assertFalse(Gate::forUser(User::factory()->unverified()->create())->allows('create', Post::class));
    }
}
