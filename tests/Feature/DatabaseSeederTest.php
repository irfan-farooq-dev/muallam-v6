<?php

namespace Tests\Feature;

use App\Models\Like;
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

        $this->assertSame(0, Like::query()->join('posts', 'posts.id', '=', 'likes.post_id')->whereColumn('likes.user_id', 'posts.user_id')->count());

        Post::withCount(['likes as actual_likes'])->get()->each(
            fn (Post $post) => $this->assertSame($post->actual_likes, $post->likes_count)
        );
    }
}
