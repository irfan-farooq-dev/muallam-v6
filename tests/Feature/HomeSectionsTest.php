<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeSectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_slider_shows_the_newest_public_posts_with_a_picture(): void
    {
        Post::factory()->published()->create(['title' => 'Older picture post', 'featured_image' => 'featured/1/a.webp', 'published_at' => now()->subDays(2)]);
        Post::factory()->published()->create(['title' => 'Newer picture post', 'featured_image' => 'featured/1/b.webp', 'published_at' => now()->subDay()]);
        Post::factory()->published()->create(['title' => 'Text only post']);
        Post::factory()->draft()->create(['title' => 'Draft picture post', 'featured_image' => 'featured/1/c.webp']);

        $response = $this->get(route('home'))->assertOk()
            ->assertSee('swiper-bundle.min.js', false)
            ->assertSee('aria-label="Featured posts"', false);

        $slider = $this->between($response->getContent(), 'class="featured-slider', 'swiper-pagination');
        $this->assertStringContainsString('Newer picture post', $slider);
        $this->assertStringContainsString('Older picture post', $slider);
        $this->assertStringNotContainsString('Text only post', $slider);
        $this->assertStringNotContainsString('Draft picture post', $slider);
        $this->assertLessThan(strpos($slider, 'Older picture post'), strpos($slider, 'Newer picture post'));
        $this->assertStringContainsString('data-loop="0"', $slider);
    }

    public function test_slider_is_left_out_until_two_posts_have_a_picture(): void
    {
        Post::factory()->published()->create(['featured_image' => 'featured/1/a.webp']);

        $this->get(route('home'))
            ->assertDontSee('featured-slider', false)
            ->assertDontSee('swiper-bundle.min.js', false);
    }

    public function test_slider_shows_at_most_five_posts(): void
    {
        Post::factory()->published()->count(7)->sequence(fn ($sequence) => ['featured_image' => "featured/1/{$sequence->index}.webp"])->create();

        $slider = $this->between($this->get(route('home'))->getContent(), 'class="featured-slider', 'swiper-pagination');

        $this->assertSame(5, substr_count($slider, 'class="swiper-slide"'));
        $this->assertStringContainsString('data-loop="1"', $slider);
    }

    public function test_categories_with_three_or_more_posts_get_a_section_of_up_to_four(): void
    {
        $science = Category::factory()->create(['name' => 'Science', 'slug' => 'science', 'sort_order' => 1]);
        $sports = Category::factory()->create(['name' => 'Sports', 'slug' => 'sports', 'sort_order' => 2]);
        Post::factory()->published()->for($science)->count(5)->sequence(fn ($sequence) => ['title' => "Science post {$sequence->index}", 'published_at' => now()->subDays($sequence->index + 1)])->create();
        Post::factory()->published()->for($sports)->count(2)->create();
        Post::factory()->draft()->for($sports)->create();

        $response = $this->get(route('home'))->assertOk()
            ->assertSee('id="category-section-science"', false)
            ->assertDontSee('id="category-section-sports"', false);

        $section = $this->between($response->getContent(), 'id="category-section-science"', '</section>');
        $this->assertStringContainsString(route('categories.show', 'science'), $section);
        foreach ([0, 1, 2, 3] as $index) {
            $this->assertStringContainsString("Science post {$index}", $section);
        }
        $this->assertStringNotContainsString('Science post 4', $section);
        $this->assertSame(3, substr_count($section, 'class="post-mini '));
    }

    public function test_hidden_categories_get_no_section_and_later_pages_show_no_extras(): void
    {
        $hidden = Category::factory()->inactive()->create(['slug' => 'hidden-topic']);
        $science = Category::factory()->create(['slug' => 'science']);
        Post::factory()->published()->for($hidden)->count(3)->create();
        Post::factory()->published()->for($science)->count(3)->create(['featured_image' => 'featured/1/a.webp']);
        Post::factory()->published()->count(10)->create();

        $this->get(route('home'))->assertDontSee('id="category-section-hidden-topic"', false)->assertSee('id="category-section-science"', false);
        $this->get(route('home', ['page' => 2]))
            ->assertOk()
            ->assertDontSee('home-category-section', false)
            ->assertDontSee('featured-slider', false);
    }

    private function between(string $html, string $start, string $end): string
    {
        $from = strpos($html, $start);
        $this->assertNotFalse($from, "Missing {$start}");

        return substr($html, $from, strpos($html, $end, $from) - $from);
    }
}
