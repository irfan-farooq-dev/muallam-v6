<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShareButtonsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_post_has_share_links_for_each_network(): void
    {
        $post = Post::factory()->published()->create(['title' => 'Stars & planets', 'slug' => 'stars-and-planets']);
        $url = rawurlencode(route('posts.show', $post));
        $title = rawurlencode('Stars & planets');

        $response = $this->get(route('posts.show', $post))->assertOk();

        foreach ([
            "https://www.facebook.com/sharer/sharer.php?u={$url}",
            'https://wa.me/?text='.rawurlencode('Stars & planets '.route('posts.show', $post)),
            "https://www.linkedin.com/sharing/share-offsite/?url={$url}",
            "https://x.com/intent/post?url={$url}&amp;text={$title}",
            "https://t.me/share/url?url={$url}&amp;text={$title}",
        ] as $shareLink) {
            $response->assertSee('href="'.$shareLink.'"', false);
        }

        $response->assertSee('bi-facebook', false)
            ->assertSee('bi-whatsapp', false)
            ->assertSee('bi-linkedin', false)
            ->assertSee('bi-twitter-x', false)
            ->assertSee('aria-label="Copy link"', false)
            ->assertSee('rel="noopener noreferrer"', false);
    }

    public function test_posts_that_are_not_public_have_no_share_buttons(): void
    {
        $author = User::factory()->create();

        foreach ([
            Post::factory()->draft()->for($author)->create(),
            Post::factory()->private()->for($author)->create(),
            Post::factory()->restricted()->for($author)->create(),
            Post::factory()->scheduled()->for($author)->create(),
        ] as $post) {
            $this->actingAs($author)->get(route('posts.show', $post))
                ->assertOk()
                ->assertDontSee('share-buttons', false);
        }
    }
}
