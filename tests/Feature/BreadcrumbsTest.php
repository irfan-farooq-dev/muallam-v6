<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BreadcrumbsTest extends TestCase
{
    use RefreshDatabase;

    private function crumbLink(string $url, string $label): string
    {
        return '<a href="'.$url.'" itemprop="item"><span itemprop="name">'.$label.'</span></a>';
    }

    public function test_post_page_trail_is_home_then_category_then_post(): void
    {
        $category = Category::factory()->create(['name' => 'Science', 'slug' => 'science']);
        $post = Post::factory()->for($category)->published()->create(['title' => 'How stars form']);

        $this->get(route('posts.show', $post))
            ->assertSee('aria-label="breadcrumb"', false)
            ->assertSee($this->crumbLink(route('home'), 'Home'), false)
            ->assertSee($this->crumbLink(route('categories.show', 'science'), 'Science'), false)
            ->assertSee('<span itemprop="name">How stars form</span>', false);
    }

    public function test_post_without_category_skips_the_category_crumb(): void
    {
        $post = Post::factory()->withoutCategory()->published()->create(['title' => 'Loose post']);

        $this->get(route('posts.show', $post))
            ->assertSee($this->crumbLink(route('home'), 'Home'), false)
            ->assertSee('<span itemprop="name">Loose post</span>', false);
    }

    public function test_listing_pages_have_trails_back_home(): void
    {
        Category::factory()->create(['name' => 'Science', 'slug' => 'science']);
        User::factory()->withPublicBio()->create(['name' => 'Irfan Farooq', 'slug' => 'irfan-farooq']);

        $this->get(route('categories.index'))->assertSee($this->crumbLink(route('home'), 'Home'), false);
        $this->get(route('categories.show', 'science'))
            ->assertSee($this->crumbLink(route('categories.index'), 'Categories'), false);
        $this->get(route('search', ['q' => 'stars']))->assertSee($this->crumbLink(route('home'), 'Home'), false);
        $this->get(route('users.posts', 'irfan-farooq'))->assertSee($this->crumbLink(route('home'), 'Home'), false);
        $this->get(route('users.bio', 'irfan-farooq'))
            ->assertSee($this->crumbLink(route('users.posts', 'irfan-farooq'), 'Irfan Farooq'), false);
    }

    public function test_likes_use_the_thumbs_up_icon(): void
    {
        $post = Post::factory()->published()->create();

        $this->get(route('home'))->assertSee('bi-hand-thumbs-up', false)->assertDontSee('bi-heart', false);
        $this->get(route('posts.show', $post))->assertSee('bi-hand-thumbs-up', false)->assertDontSee('bi-heart', false);
    }
}
