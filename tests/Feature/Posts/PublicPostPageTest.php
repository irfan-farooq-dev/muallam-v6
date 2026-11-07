<?php

namespace Tests\Feature\Posts;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use App\Services\PostSlugGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicPostPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_can_read_a_published_post(): void
    {
        $author = User::factory()->withPublicBio()->create(['name' => 'Irfan Farooq', 'slug' => 'irfan-farooq']);
        $post = Post::factory()->for($author)->for(Category::factory()->create(['name' => 'Science']))->published()->create([
            'title' => 'How stars form',
            'summary' => 'From dust to light.',
            'body' => '<h2>Clouds</h2><p>Gravity pulls gas together.</p>',
        ]);

        $this->get(route('posts.show', $post))
            ->assertOk()
            ->assertSee('<title>How stars form | Muallam</title>', false)
            ->assertSee('content="From dust to light."', false)
            ->assertSee('<h2>Clouds</h2><p>Gravity pulls gas together.</p>', false)
            ->assertSee('Science')
            ->assertSee('Irfan Farooq')
            ->assertSee(route('users.posts', 'irfan-farooq'))
            ->assertSee(route('users.bio', 'irfan-farooq'))
            ->assertDontSee('Not public.');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function hiddenStates(): array
    {
        return ['draft' => ['draft'], 'scheduled' => ['scheduled'], 'private' => ['private'], 'restricted' => ['restricted']];
    }

    #[DataProvider('hiddenStates')]
    public function test_hidden_posts_are_not_found_for_others(string $state): void
    {
        $post = Post::factory()->{$state}()->create();

        $this->get(route('posts.show', $post))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('posts.show', $post))->assertNotFound();
    }

    public function test_author_can_view_their_hidden_post_with_a_notice(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->for($author)->draft()->create(['title' => 'Work in progress']);

        $this->actingAs($author)->get(route('posts.show', $post))
            ->assertOk()
            ->assertSee('Not public.')
            ->assertSee('This post is draft')
            ->assertSee('noindex', false)
            ->assertSee(route('posts.edit', $post));
    }

    public function test_scheduled_notice_shows_the_go_live_time(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->for($author)->scheduled()->create();

        $this->actingAs($author)->get(route('posts.show', $post))
            ->assertSee('Scheduled to go live on '.$post->published_at->format('j M Y, g:i a'));
    }

    public function test_views_are_counted_once_per_visitor_session(): void
    {
        $post = Post::factory()->published()->create(['views_count' => 10]);

        $this->get(route('posts.show', $post))->assertSee('11 views');
        $this->get(route('posts.show', $post))->assertSee('11 views');

        $this->assertSame(11, $post->fresh()->views_count);
    }

    public function test_counting_a_view_does_not_change_the_updated_time(): void
    {
        $post = Post::factory()->published()->create(['updated_at' => now()->subWeek()->startOfSecond()]);
        $before = $post->updated_at;

        $this->get(route('posts.show', $post));

        $this->assertTrue($post->fresh()->updated_at->equalTo($before));
    }

    public function test_authors_own_views_and_hidden_posts_are_not_counted(): void
    {
        $author = User::factory()->create();
        $live = Post::factory()->for($author)->published()->create();
        $draft = Post::factory()->for($author)->draft()->create();

        $this->actingAs($author)->get(route('posts.show', $live));
        $this->actingAs($author)->get(route('posts.show', $draft));

        $this->assertSame(0, $live->fresh()->views_count);
        $this->assertSame(0, $draft->fresh()->views_count);
    }

    public function test_page_suggests_more_posts_from_the_category_and_author(): void
    {
        $author = User::factory()->create(['name' => 'Irfan Farooq']);
        $category = Category::factory()->create(['name' => 'Science']);
        $post = Post::factory()->for($author)->for($category)->published()->create();
        Post::factory()->for($category)->published()->create(['title' => 'Same category post']);
        Post::factory()->for($author)->published()->create(['title' => 'Same author post']);
        Post::factory()->for($category)->draft()->create(['title' => 'Hidden draft']);

        $this->get(route('posts.show', $post))
            ->assertSee('More in Science')
            ->assertSee('Same category post')
            ->assertSee('More from Irfan Farooq')
            ->assertSee('Same author post')
            ->assertDontSee('Hidden draft');
    }

    public function test_post_cards_now_link_to_the_post(): void
    {
        $post = Post::factory()->published()->create(['title' => 'Linked post']);

        $this->get(route('home'))->assertSee(route('posts.show', $post));
    }

    public function test_reserved_words_are_never_used_as_post_slugs(): void
    {
        $slugs = app(PostSlugGenerator::class);

        $this->assertSame('create-2', $slugs->generate('Create'));
        $this->assertSame('preview-2', $slugs->generate('Preview'));
        $this->assertSame('images-2', $slugs->generate('Images'));
    }
}
