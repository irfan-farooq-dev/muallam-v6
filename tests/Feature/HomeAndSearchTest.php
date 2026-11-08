<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use App\Models\UserSlugHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeAndSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_lists_public_posts_newest_first_with_category_chips(): void
    {
        Category::factory()->create(['name' => 'Science', 'slug' => 'science']);
        Post::factory()->published()->create(['title' => 'Older post', 'published_at' => now()->subDays(3)]);
        Post::factory()->published()->create(['title' => 'Newer post', 'published_at' => now()->subDay()]);
        Post::factory()->draft()->create(['title' => 'Draft post']);
        Post::factory()->scheduled()->create(['title' => 'Future post']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<aside class="site-sidebar" aria-label="Categories">', false)
            ->assertSee(route('categories.show', 'science'))
            ->assertSeeInOrder(['Newer post', 'Older post'])
            ->assertDontSee('Draft post')
            ->assertDontSee('Future post');
    }

    public function test_post_page_sidebar_highlights_the_posts_category_instead_of_a_label_above_the_title(): void
    {
        $science = Category::factory()->create(['name' => 'Science', 'slug' => 'science']);
        Category::factory()->create(['name' => 'Sports', 'slug' => 'sports']);
        $post = Post::factory()->for($science)->published()->create();

        $response = $this->get(route('posts.show', $post))->assertOk()->assertDontSee('post-article-category', false);

        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('categories.show', 'science'), '/').'"\s+class="active"\s+aria-current="true"/', $response->getContent());
        $this->assertDoesNotMatchRegularExpression('/href="'.preg_quote(route('categories.show', 'sports'), '/').'"\s+class="active"/', $response->getContent());
    }

    public function test_signed_in_users_get_a_dashboard_link_in_the_top_menu(): void
    {
        $dashboardLink = '<li><a href="'.route('dashboard').'" class="">Dashboard</a></li>';

        $this->get(route('home'))->assertDontSee($dashboardLink, false);
        $this->actingAs(User::factory()->create())->get(route('home'))->assertSee($dashboardLink, false);
    }

    public function test_start_writing_sends_guests_to_sign_in(): void
    {
        $this->get(route('home'))
            ->assertSee('<a href="'.route('login').'" class="btn btn-outline-accent">Start writing</a>', false)
            ->assertSee('<li><a href="'.route('login').'">Start writing</a></li>', false);
    }

    public function test_post_card_top_opens_the_post_and_author_strip_opens_the_author(): void
    {
        $public = User::factory()->withPublicBio()->create(['name' => 'Open Author', 'slug' => 'open-author']);
        $private = User::factory()->create(['name' => 'Quiet Author', 'slug' => 'quiet-author', 'is_bio_public' => false]);
        $first = Post::factory()->published()->for($public)->create(['title' => 'Post by open author']);
        Post::factory()->published()->for($private)->create(['title' => 'Post by quiet author']);

        $response = $this->get(route('home'))->assertOk();

        $this->assertMatchesRegularExpression('/<a\s+href="'.preg_quote(route('posts.show', $first), '/').'"\s+class="post-card-main/', $response->getContent());
        // Public bio: the strip opens the bio. Private bio: it opens the author's posts (the bio page would be a 404).
        $response->assertSee('href="'.route('users.bio', 'open-author').'" title="About Open Author"', false)
            ->assertSee('href="'.route('users.posts', 'quiet-author').'" title="About Quiet Author"', false)
            ->assertDontSee(route('users.bio', 'quiet-author'));
    }

    public function test_home_paginates_and_hides_the_banner_after_the_first_page(): void
    {
        Post::factory()->published()->count(13)->create();

        $this->assertCount(12, $this->get(route('home'))->assertSee('Learn something new today')->viewData('posts'));
        $this->get(route('home', ['page' => 2]))->assertDontSee('Learn something new today');
    }

    public function test_search_matches_title_summary_and_content_of_public_posts(): void
    {
        Post::factory()->published()->create(['title' => 'Laravel queues explained', 'summary' => null, 'body' => '<p>x</p>']);
        Post::factory()->published()->create(['title' => 'Other', 'summary' => 'All about Laravel', 'body' => '<p>x</p>']);
        Post::factory()->published()->create(['title' => 'Third', 'summary' => null, 'body' => '<p>We use laravel here</p>']);
        Post::factory()->draft()->create(['title' => 'Laravel draft']);
        Post::factory()->published()->create(['title' => 'Cooking rice', 'summary' => null, 'body' => '<p>x</p>']);

        $this->get(route('search', ['q' => 'laravel']))
            ->assertOk()
            ->assertSee('3 posts found')
            ->assertSee('Laravel queues explained')
            ->assertSee('Other')
            ->assertSee('Third')
            ->assertDontSee('Laravel draft')
            ->assertDontSee('Cooking rice');
    }

    public function test_every_search_word_must_match(): void
    {
        Post::factory()->published()->create(['title' => 'Laravel queues', 'body' => '<p>x</p>']);
        Post::factory()->published()->create(['title' => 'Laravel routing', 'body' => '<p>x</p>']);

        $this->get(route('search', ['q' => 'laravel queues']))
            ->assertSee('Laravel queues')
            ->assertDontSee('Laravel routing');
    }

    public function test_search_treats_percent_and_underscore_as_plain_text(): void
    {
        Post::factory()->published()->create(['title' => 'Plain title', 'body' => '<p>x</p>']);

        $this->get(route('search', ['q' => '%%']))->assertSee('No posts match');
    }

    public function test_too_short_search_asks_for_more_letters(): void
    {
        $this->get(route('search', ['q' => 'a']))->assertOk()->assertSee('Type at least 2 letters');
    }

    public function test_author_page_lists_their_public_posts_even_with_a_hidden_bio(): void
    {
        $author = User::factory()->create(['name' => 'Irfan Farooq', 'slug' => 'irfan-farooq', 'is_bio_public' => false]);
        Post::factory()->for($author)->published()->create(['title' => 'Public one']);
        Post::factory()->for($author)->draft()->create(['title' => 'Draft one']);
        Post::factory()->published()->create(['title' => 'Someone else']);

        $this->get(route('users.posts', 'irfan-farooq'))
            ->assertOk()
            ->assertSee('<title>Posts by Irfan Farooq | Muallam</title>', false)
            ->assertSee('1 post')
            ->assertSee('Public one')
            ->assertDontSee('Draft one')
            ->assertDontSee('Someone else')
            ->assertDontSee(route('users.bio', 'irfan-farooq'));
    }

    public function test_author_page_old_slug_redirects_and_inactive_author_is_not_found(): void
    {
        $author = User::factory()->create(['slug' => 'new-slug']);
        UserSlugHistory::factory()->for($author)->create(['slug' => 'old-slug']);
        User::factory()->inactive()->create(['slug' => 'blocked-author']);

        $this->get(route('users.posts', 'old-slug'))->assertStatus(301)->assertRedirect(route('users.posts', 'new-slug'));
        $this->get(route('users.posts', 'blocked-author'))->assertNotFound();
        $this->get(route('users.posts', 'nobody'))->assertNotFound();
    }

    public function test_bio_page_links_to_the_authors_posts(): void
    {
        User::factory()->withPublicBio()->create(['slug' => 'irfan-farooq']);

        $this->get(route('users.bio', 'irfan-farooq'))->assertSee(route('users.posts', 'irfan-farooq'));
    }

    public function test_header_has_a_search_box(): void
    {
        $this->get(route('home'))->assertSee('action="'.route('search').'"', false);
    }
}
