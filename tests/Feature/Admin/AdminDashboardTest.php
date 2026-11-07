<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_regular_members_are_refused(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'))
            ->assertForbidden()
            ->assertSee('This area is for site administrators.');
    }

    public function test_unverified_admin_must_verify_first(): void
    {
        $this->actingAs(User::factory()->admin()->unverified()->create())
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_admin_sees_the_dashboard_in_the_admin_layout(): void
    {
        $this->actingAs(User::factory()->admin()->create(['name' => 'Site Admin']))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('<title>Dashboard | Muallam admin</title>', false)
            ->assertSee('core-muallam.css', false)
            ->assertSee('id="layout-menu"', false)
            ->assertSee('Site Admin')
            ->assertSee('noindex, nofollow', false)
            ->assertSee('activityChart', false);
    }

    public function test_totals_count_the_whole_site(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(2)->create();
        User::factory()->unverified()->create();
        User::factory()->inactive()->create(['created_at' => now()->subDays(20)]);

        $category = Category::factory()->create();
        Category::factory()->inactive()->create();
        $post = Post::factory()->for($category)->published()->create(['views_count' => 1200, 'likes_count' => 7]);
        Post::factory()->for($category)->published()->create(['views_count' => 34, 'likes_count' => 2]);
        Post::factory()->for($category)->scheduled()->create();
        Post::factory()->for($category)->draft()->count(3)->create();
        Post::factory()->for($category)->private()->create();
        Post::factory()->for($category)->restricted()->create();
        Comment::factory()->for($post)->count(2)->create();
        Comment::factory()->for($post)->hidden()->create();

        $userCount = User::count();

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertSee('data-stat="users">'.$userCount.'<', false)
            ->assertSee('data-stat="published">2<', false)
            ->assertSee('8 posts in total')
            ->assertSee('data-stat="posts-scheduled">1<', false)
            ->assertSee('data-stat="posts-drafts">3<', false)
            ->assertSee('data-stat="posts-private">1<', false)
            ->assertSee('data-stat="posts-restricted">1<', false)
            ->assertSee('data-stat="comments">2<', false)
            ->assertSee('1 hidden')
            ->assertSee('data-stat="views">1,234<', false)
            ->assertSee('9 likes')
            ->assertSee('data-stat="users-admins">1<', false)
            ->assertSee('data-stat="users-unverified">1<', false)
            ->assertSee('data-stat="users-inactive">1<', false)
            ->assertSee('data-stat="categories">1<', false);
    }

    public function test_chart_counts_sign_ups_and_published_posts_per_day(): void
    {
        $this->freezeTime();
        $admin = User::factory()->admin()->create();
        User::factory()->count(2)->create(['created_at' => now()->subDays(3)]);
        User::factory()->create(['created_at' => now()->subDays(45)]);
        Post::factory()->for($admin)->published()->create(['published_at' => now()->subDays(3)]);
        Post::factory()->for($admin)->scheduled()->create();

        $chart = $this->actingAs($admin)->get(route('admin.dashboard'))->viewData('chart');

        $this->assertCount(30, $chart['labels']);
        $this->assertSame(now()->subDays(3)->format('j M'), $chart['labels'][26]);
        $this->assertSame(2, $chart['signups'][26]);
        $this->assertSame(1, $chart['posts'][26]);
        $this->assertSame(1, end($chart['signups']));
        $this->assertSame(1, array_sum($chart['posts']));
    }

    public function test_recent_lists_show_latest_members_posts_and_comments(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['name' => 'Newest Member', 'created_at' => now()->addMinute()]);
        Post::factory()->draft()->create(['title' => 'A fresh draft']);
        Comment::factory()->hidden()->create(['body' => 'A hidden remark']);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertSee('Newest Member')
            ->assertSee('A fresh draft')
            ->assertSee('A hidden remark')
            ->assertSee('Hidden');
    }

    public function test_header_menu_shows_the_admin_link_only_to_admins(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('home'))->assertSee(route('admin.dashboard'));
        $this->actingAs(User::factory()->create())->get(route('home'))->assertDontSee(route('admin.dashboard'));
    }
}
