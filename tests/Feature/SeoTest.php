<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_has_canonical_and_link_preview_tags(): void
    {
        $this->get(route('home'))
            ->assertSee('<link rel="canonical" href="'.route('home').'">', false)
            ->assertSee('<meta property="og:type" content="website">', false)
            ->assertSee('<meta property="og:image" content="'.asset('img/og-default.png').'">', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
            ->assertSee('<meta name="robots" content="index, follow">', false);
    }

    public function test_paginated_pages_keep_their_page_number_in_the_canonical_url(): void
    {
        Post::factory()->published()->count(13)->create();

        $this->get(route('home', ['page' => 2]))
            ->assertSee('<link rel="canonical" href="'.route('home').'?page=2">', false);
    }

    public function test_post_page_describes_itself_as_an_article(): void
    {
        $author = User::factory()->create(['name' => 'Irfan Farooq', 'slug' => 'irfan-farooq']);
        $post = Post::factory()->for($author)->for(Category::factory()->create(['name' => 'Science']))->published()->create([
            'title' => 'How stars form',
            'summary' => 'From dust to light.',
            'featured_image' => 'posts/1/featured/stars.webp',
        ]);

        $response = $this->get(route('posts.show', [$post, 'utm_source' => 'whatsapp']));

        $response
            ->assertSee('<link rel="canonical" href="'.route('posts.show', $post).'">', false)
            ->assertSee('<meta property="og:type" content="article">', false)
            ->assertSee('<meta property="og:title" content="How stars form">', false)
            ->assertSee('<meta property="og:description" content="From dust to light.">', false)
            ->assertSee('<meta property="og:image" content="'.$post->featuredImageUrl().'">', false)
            ->assertSee('<meta property="article:section" content="Science">', false)
            ->assertSee('article:published_time', false);

        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $response->getContent(), $match);
        $data = json_decode($match[1], true);
        $this->assertSame('https://schema.org', $data['@context']);
        $this->assertSame('BlogPosting', $data['@type']);
        $this->assertSame('How stars form', $data['headline']);
        $this->assertSame('Irfan Farooq', $data['author']['name']);
        $this->assertSame(route('posts.show', $post), $data['mainEntityOfPage']);
    }

    public function test_post_without_image_uses_the_default_share_image(): void
    {
        $post = Post::factory()->published()->create(['featured_image' => null]);

        $this->get(route('posts.show', $post))->assertSee('<meta property="og:image" content="'.asset('img/og-default.png').'">', false);
    }

    public function test_private_pages_ask_search_engines_not_to_index_them(): void
    {
        $user = User::factory()->create();
        $draft = Post::factory()->for($user)->draft()->create();
        $noIndex = '<meta name="robots" content="noindex, nofollow">';

        $this->actingAs($user)->get(route('dashboard'))->assertSee($noIndex, false);
        $this->actingAs($user)->get(route('posts.show', $draft))->assertSee($noIndex, false)->assertDontSee('application/ld+json', false);
        $this->post('/logout');
        $this->get(route('login'))->assertSee('<meta name="robots" content="noindex, follow">', false);
        $this->get(route('search', ['q' => 'stars']))->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    public function test_sitemap_lists_public_pages_only(): void
    {
        $category = Category::factory()->create(['slug' => 'science']);
        Category::factory()->inactive()->create(['slug' => 'hidden-topic']);
        $author = User::factory()->withPublicBio()->create(['slug' => 'irfan-farooq']);
        User::factory()->create(['slug' => 'no-posts-yet']);
        $live = Post::factory()->for($author)->for($category)->published()->create(['slug' => 'live-post']);
        Post::factory()->for($author)->draft()->create(['slug' => 'draft-post']);
        Post::factory()->for($author)->scheduled()->create(['slug' => 'future-post']);
        Post::factory()->for($author)->restricted()->create(['slug' => 'secret-post']);

        $response = $this->get(route('sitemap'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $xml = $response->getContent();
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
        foreach ([route('home'), route('categories.index'), route('categories.show', 'science'), route('posts.show', $live), route('users.posts', 'irfan-farooq'), route('users.bio', 'irfan-farooq')] as $url) {
            $this->assertStringContainsString('<loc>'.$url.'</loc>', $xml);
        }
        foreach (['hidden-topic', 'draft-post', 'future-post', 'secret-post', 'no-posts-yet'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $xml);
        }
    }

    public function test_robots_txt_keeps_search_engines_out_except_on_the_live_site(): void
    {
        $this->get(route('robots'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee("User-agent: *\nDisallow: /\n", false);

        $this->app['env'] = 'production';

        $this->get(route('robots'))
            ->assertSee('Disallow: /my/', false)
            ->assertSee('Disallow: /posts/*/access', false)
            ->assertSee('Sitemap: '.route('sitemap'), false)
            ->assertDontSee("Disallow: /\n", false);
    }
}
