<?php

namespace Tests\Unit;

use App\Models\Post;
use PHPUnit\Framework\TestCase;

class PostCardSummaryTest extends TestCase
{
    public function test_card_uses_the_summary_when_there_is_one(): void
    {
        $post = new Post(['summary' => 'Hand-written summary.', 'body' => '<p>Body text.</p>']);

        $this->assertSame('Hand-written summary.', $post->cardSummary());
    }

    public function test_card_falls_back_to_the_start_of_the_post_as_plain_text(): void
    {
        $post = new Post(['summary' => null, 'body' => '<h2>Intro</h2><p>Tom &amp; Jerry</p><p><img src="/a.webp" alt="x">Second paragraph that is long enough to be cut.</p>']);

        $this->assertSame('Intro Tom & Jerry Second...', $post->cardSummary(24));
    }

    public function test_card_has_no_text_for_an_image_only_post(): void
    {
        $this->assertNull((new Post(['body' => '<p><img src="/a.webp" alt="x"></p>']))->cardSummary());
    }
}
