<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_demo_accounts_and_consistent_counters(): void
    {
        $this->seed();

        $this->assertTrue(User::where('email', 'admin@muallam.test')->sole()->isAdmin());
        $this->assertModelExists(User::where('slug', 'demo-author')->sole());
        $this->assertTrue(Post::publiclyVisible()->exists());

        Post::withCount(['likes as actual_likes'])->get()->each(
            fn (Post $post) => $this->assertSame($post->actual_likes, $post->likes_count)
        );
    }
}
