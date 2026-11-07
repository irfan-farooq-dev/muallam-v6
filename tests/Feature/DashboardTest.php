<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_sent_to_sign_in(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_dashboard_renders_in_the_member_layout(): void
    {
        $user = User::factory()->create(['name' => 'Irfan Farooq']);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<title>Dashboard | Muallam</title>', false)
            ->assertSee('Hi Irfan Farooq,')
            ->assertSee('aria-label="Member area"', false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_unverified_user_can_use_the_dashboard_and_sees_the_reminder(): void
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Please verify your email address.');
    }

    public function test_counts_only_the_users_own_posts_by_visibility(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $mine = Post::factory()->for($user)->recycle($category);

        $mine->published()->count(3)->create();
        $mine->scheduled()->count(2)->create();
        $mine->draft()->count(4)->create();
        $mine->private()->create();
        $mine->restricted()->create();
        Post::factory()->published()->count(5)->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertSee('(11 in total)')
            ->assertSee('data-stat="published">3<', false)
            ->assertSee('data-stat="scheduled">2<', false)
            ->assertSee('data-stat="drafts">4<', false)
            ->assertSee('data-stat="private-restricted">2<', false);
    }

    public function test_engagement_totals_add_up_the_users_posts(): void
    {
        $user = User::factory()->create();
        Post::factory()->for($user)->published()->create(['views_count' => 1200, 'likes_count' => 7, 'comments_count' => 3]);
        Post::factory()->for($user)->published()->create(['views_count' => 34, 'likes_count' => 2, 'comments_count' => 1]);
        Post::factory()->published()->create(['views_count' => 999, 'likes_count' => 99, 'comments_count' => 99]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertSee('data-stat="views">1,234<', false)
            ->assertSee('data-stat="likes">9<', false)
            ->assertSee('data-stat="comments">4<', false);
    }

    public function test_new_user_sees_zeros_and_an_empty_state(): void
    {
        $this->actingAs(User::factory()->create())->get(route('dashboard'))
            ->assertSee('(0 in total)')
            ->assertSee('data-stat="published">0<', false)
            ->assertSee('data-stat="views">0<', false)
            ->assertSee("You haven't written anything yet.", false);
    }

    public function test_recent_list_shows_the_five_latest_updated_own_posts(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 6) as $day) {
            Post::factory()->for($user)->create(['title' => "My post {$day}", 'updated_at' => now()->subDays(10 - $day)]);
        }
        Post::factory()->create(['title' => 'Someone else post', 'updated_at' => now()]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertSeeInOrder(['My post 6', 'My post 5', 'My post 4', 'My post 3', 'My post 2'])
            ->assertDontSee('My post 1')
            ->assertDontSee('Someone else post');
    }

    public function test_recent_list_labels_each_post_status(): void
    {
        $user = User::factory()->create();
        Post::factory()->for($user)->scheduled()->create();
        Post::factory()->for($user)->restricted()->create();
        Post::factory()->for($user)->withoutCategory()->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertSee('Scheduled')
            ->assertSee('Restricted')
            ->assertSee('Draft')
            ->assertSee('Uncategorised');
    }

    public function test_header_menu_links_to_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())->get(route('home'))
            ->assertSee(route('dashboard'));
    }
}
