<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CategoryPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_menu_lists_active_categories_in_order(): void
    {
        Category::factory()->create(['name' => 'Second', 'slug' => 'second', 'sort_order' => 2]);
        Category::factory()->create(['name' => 'First', 'slug' => 'first', 'sort_order' => 1]);
        Category::factory()->inactive()->create(['name' => 'Hidden Topic', 'slug' => 'hidden-topic']);

        $this->get(route('home'))
            ->assertSeeInOrder(['Categories', 'First', 'Second', 'All categories'])
            ->assertDontSee('Hidden Topic');
    }

    public function test_header_menu_updates_when_a_category_changes(): void
    {
        $category = Category::factory()->create(['name' => 'Databases']);
        $this->get(route('home'))->assertSee('Databases');

        $category->update(['name' => 'Data & SQL']);

        $this->get(route('home'))->assertSee('Data &amp; SQL', false)->assertDontSee('>Databases<', false);
    }

    public function test_header_menu_works_when_the_cache_stores_serialized_values(): void
    {
        config(['cache.stores.array.serialize' => true]);
        Cache::forgetDriver('array');
        Category::factory()->create(['name' => 'Databases']);

        $this->get(route('home'))->assertOk()->assertSee('Databases');
        $this->get(route('categories.index'))->assertOk()->assertSee('Databases');
    }

    public function test_header_has_no_categories_menu_when_there_are_none(): void
    {
        $this->get(route('home'))->assertDontSee('All categories');
    }

    public function test_categories_page_lists_active_categories_with_public_post_counts(): void
    {
        $databases = Category::factory()->create(['name' => 'Databases', 'slug' => 'databases']);
        $career = Category::factory()->create(['name' => 'Career', 'slug' => 'career']);
        Category::factory()->inactive()->create(['name' => 'Hidden Topic']);

        Post::factory()->for($databases)->published()->count(2)->create();
        Post::factory()->for($databases)->draft()->create();
        Post::factory()->for($databases)->scheduled()->create();
        Post::factory()->for($career)->published()->create();

        $this->get(route('categories.index'))
            ->assertOk()
            ->assertSee('data-posts-count="databases">', false)
            ->assertSeeInOrder(['Databases', '2 posts'])
            ->assertSeeInOrder(['Career', '1 post'])
            ->assertDontSee('Hidden Topic');
    }

    public function test_category_page_shows_only_its_publicly_visible_posts_newest_first(): void
    {
        $category = Category::factory()->create(['name' => 'Databases', 'slug' => 'databases']);
        Post::factory()->for($category)->published()->create(['title' => 'Older post', 'published_at' => now()->subDays(5)]);
        Post::factory()->for($category)->published()->create(['title' => 'Newer post', 'published_at' => now()->subDay()]);
        Post::factory()->for($category)->draft()->create(['title' => 'Draft post']);
        Post::factory()->for($category)->scheduled()->create(['title' => 'Scheduled post']);
        Post::factory()->for($category)->private()->create(['title' => 'Private post']);
        Post::factory()->for($category)->restricted()->create(['title' => 'Restricted post']);
        Post::factory()->published()->create(['title' => 'Post in another category']);

        $this->get(route('categories.show', 'databases'))
            ->assertOk()
            ->assertSee('<title>Databases | Muallam</title>', false)
            ->assertSeeInOrder(['Newer post', 'Older post'])
            ->assertDontSee('Draft post')
            ->assertDontSee('Scheduled post')
            ->assertDontSee('Private post')
            ->assertDontSee('Restricted post')
            ->assertDontSee('Post in another category');
    }

    public function test_category_page_paginates_twelve_posts_per_page(): void
    {
        $category = Category::factory()->create(['slug' => 'databases']);
        Post::factory()->for($category)->published()->count(13)->create();

        $response = $this->get(route('categories.show', 'databases'));
        $this->assertCount(12, $response->viewData('posts'));
        $response->assertSee('?page=2', false);

        $this->assertCount(1, $this->get(route('categories.show', ['category' => 'databases', 'page' => 2]))->viewData('posts'));
    }

    public function test_author_names_link_to_their_posts_page(): void
    {
        $category = Category::factory()->create(['slug' => 'databases']);
        $author = User::factory()->create(['name' => 'Private Author', 'slug' => 'private-author']);
        Post::factory()->for($category)->for($author)->published()->create();

        $this->get(route('categories.show', 'databases'))
            ->assertSee(route('users.posts', 'private-author'))
            ->assertDontSee(route('users.bio', 'private-author'));
    }

    public function test_empty_category_shows_a_friendly_message(): void
    {
        Category::factory()->create(['name' => 'Career', 'slug' => 'career']);

        $this->get(route('categories.show', 'career'))->assertOk()->assertSee('No posts in Career yet.');
    }

    public function test_inactive_or_unknown_category_is_not_found(): void
    {
        Category::factory()->inactive()->create(['slug' => 'hidden-topic']);

        $this->get(route('categories.show', 'hidden-topic'))->assertNotFound();
        $this->get(route('categories.show', 'no-such-topic'))->assertNotFound();
    }

    public function test_category_seeder_can_run_twice_without_duplicates(): void
    {
        $this->seed(CategorySeeder::class);
        Category::where('slug', 'databases')->update(['name' => 'Renamed by mistake', 'sort_order' => 99]);

        $this->seed(CategorySeeder::class);

        $this->assertSame(count(CategorySeeder::CATEGORIES), Category::count());
        $this->assertSame(
            array_column(CategorySeeder::CATEGORIES, 'name'),
            Category::active()->pluck('name')->all(),
        );
    }
}
