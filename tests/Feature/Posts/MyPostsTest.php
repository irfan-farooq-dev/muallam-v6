<?php

namespace Tests\Feature\Posts;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyPostsTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_only_the_users_own_posts(): void
    {
        $user = User::factory()->create();
        Post::factory()->for($user)->create(['title' => 'Mine']);
        Post::factory()->create(['title' => 'Someone else']);

        $this->actingAs($user)->get(route('posts.mine'))
            ->assertOk()
            ->assertSee('Mine')
            ->assertDontSee('Someone else');
    }

    public function test_filter_tabs_show_counts_and_filter_the_list(): void
    {
        $user = User::factory()->create();
        Post::factory()->for($user)->published()->create(['title' => 'Live post']);
        Post::factory()->for($user)->scheduled()->create(['title' => 'Future post']);
        Post::factory()->for($user)->draft()->count(2)->create();
        Post::factory()->for($user)->private()->create(['title' => 'Secret post']);

        $this->actingAs($user)->get(route('posts.mine'))
            ->assertSee('data-count="all">5<', false)
            ->assertSee('data-count="published">1<', false)
            ->assertSee('data-count="scheduled">1<', false)
            ->assertSee('data-count="draft">2<', false)
            ->assertSee('data-count="private">1<', false);

        $this->actingAs($user)->get(route('posts.mine', ['status' => 'scheduled']))
            ->assertSee('Future post')
            ->assertDontSee('Live post')
            ->assertDontSee('Secret post');
    }

    public function test_search_matches_titles(): void
    {
        $user = User::factory()->create();
        Post::factory()->for($user)->create(['title' => 'Learning Laravel']);
        Post::factory()->for($user)->create(['title' => 'Cooking rice']);

        $this->actingAs($user)->get(route('posts.mine', ['search' => 'laravel']))
            ->assertSee('Learning Laravel')
            ->assertDontSee('Cooking rice');
    }

    public function test_empty_list_invites_writing_the_first_post(): void
    {
        $this->actingAs(User::factory()->create())->get(route('posts.mine'))
            ->assertSee('Write your first post');
    }

    public function test_sidebar_and_dashboard_link_to_writing(): void
    {
        $this->actingAs(User::factory()->create())->get(route('dashboard'))
            ->assertSee(route('posts.mine'))
            ->assertSee(route('posts.create'));
    }
}
