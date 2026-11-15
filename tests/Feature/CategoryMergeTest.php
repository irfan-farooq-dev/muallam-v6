<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryMergeTest extends TestCase
{
    use RefreshDatabase;

    private function runMergeMigration(): void
    {
        (require database_path('migrations/2026_11_15_073208_merge_categories_into_eight.php'))->up();
    }

    public function test_old_categories_are_merged_and_their_posts_moved(): void
    {
        $science = Category::factory()->create(['name' => 'Science', 'slug' => 'science', 'sort_order' => 10]);
        $technology = Category::factory()->create(['name' => 'Technology', 'slug' => 'technology', 'sort_order' => 11]);
        $education = Category::factory()->create(['name' => 'Education', 'slug' => 'education', 'sort_order' => 7]);
        $general = Category::factory()->create(['name' => 'General', 'slug' => 'general', 'sort_order' => 12]);
        $sciencePost = Post::factory()->for($science)->create();
        $techPost = Post::factory()->for($technology)->create();
        $educationPost = Post::factory()->for($education)->create();

        $this->runMergeMigration();

        $this->assertSame('Science & Technology', $science->fresh()->name);
        $this->assertSame('science-technology', $science->fresh()->slug);
        $this->assertModelMissing($technology);
        $this->assertModelMissing($education);
        $this->assertTrue($sciencePost->fresh()->category->is($science));
        $this->assertTrue($techPost->fresh()->category->is($science));
        $this->assertTrue($educationPost->fresh()->category->is($general));
    }

    public function test_merging_twice_or_on_an_empty_database_changes_nothing_more(): void
    {
        $this->runMergeMigration();
        $this->assertDatabaseCount('categories', 0);

        Category::factory()->create(['name' => 'Sports', 'slug' => 'sports']);
        $this->runMergeMigration();
        $this->runMergeMigration();

        $this->assertSame(['entertainment-sports'], Category::pluck('slug')->all());
    }

    public function test_seeder_creates_the_eight_categories_in_menu_order(): void
    {
        $this->seed(CategorySeeder::class);

        $this->assertSame([
            'Religion & Philosophy', 'Politics & Society', 'Culture, Art & Travel', 'Entertainment & Sports',
            'Health & Lifestyle', 'Business & Finance', 'Science & Technology', 'General',
        ], Category::orderBy('sort_order')->pluck('name')->all());
        $this->assertDatabaseHas('categories', ['slug' => 'culture-art-travel']);
    }

    public function test_old_category_addresses_redirect_to_the_merged_category(): void
    {
        Category::factory()->create(['name' => 'Entertainment & Sports', 'slug' => 'entertainment-sports']);

        $this->get('/category/sports')->assertRedirect(route('categories.show', 'entertainment-sports'))->assertStatus(301);
        $this->get('/category/no-such-category')->assertNotFound();
    }
}
