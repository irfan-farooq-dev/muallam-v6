<?php

namespace Tests\Feature\Posts;

use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_general_and_it_is_the_default(): void
    {
        $this->seed(CategorySeeder::class);

        $this->assertSame('General', Category::default()?->name);
    }

    public function test_inactive_general_is_not_used_as_default(): void
    {
        Category::factory()->inactive()->create(['slug' => 'general']);

        $this->assertNull(Category::default());
    }
}
