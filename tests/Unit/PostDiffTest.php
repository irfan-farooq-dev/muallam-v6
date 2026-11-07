<?php

namespace Tests\Unit;

use App\Services\PostDiff;
use PHPUnit\Framework\TestCase;

class PostDiffTest extends TestCase
{
    private PostDiff $diff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->diff = new PostDiff;
    }

    public function test_changed_words_are_marked_removed_then_added(): void
    {
        $this->assertSame([
            ['type' => 'same', 'text' => 'The '],
            ['type' => 'removed', 'text' => 'quick'],
            ['type' => 'added', 'text' => 'slow'],
            ['type' => 'same', 'text' => ' brown fox'],
        ], $this->diff->words('The quick brown fox', 'The slow brown fox'));
    }

    public function test_identical_and_empty_texts(): void
    {
        $this->assertSame([['type' => 'same', 'text' => 'Same']], $this->diff->words('Same', 'Same'));
        $this->assertSame([], $this->diff->words('', ''));
        $this->assertSame([['type' => 'added', 'text' => 'New summary']], $this->diff->words('', 'New summary'));
    }

    public function test_html_becomes_plain_text_blocks(): void
    {
        $this->assertSame([
            ['tag' => 'h2', 'text' => 'Heading'],
            ['tag' => 'p', 'text' => 'Bold and linked text'],
            ['tag' => 'li', 'text' => 'First'],
            ['tag' => 'li', 'text' => 'Second'],
            ['tag' => 'p', 'text' => '[Image: A cat]'],
            ['tag' => 'p', 'text' => 'Café & more'],
        ], $this->diff->blocks(
            '<h2>Heading</h2><p><strong>Bold</strong> and <a href="#">linked</a>&nbsp;text</p><p><br></p>'
            .'<ol><li>First</li><li>Second</li></ol><p><img src="/x.webp" alt="A cat"></p><p>Café &amp; more</p>'
        ));
    }

    public function test_unchanged_changed_added_and_removed_paragraphs(): void
    {
        $result = $this->diff->body(
            '<p>Keep this.</p><p>Change one word here.</p><p>Remove me entirely.</p>',
            '<p>Keep this.</p><p>Change two word here.</p><p>Completely different line.</p>',
        );

        $this->assertSame(['same', 'changed', 'removed', 'added'], array_column($result, 'status'));
        $this->assertSame([
            ['type' => 'same', 'text' => 'Change '],
            ['type' => 'removed', 'text' => 'one'],
            ['type' => 'added', 'text' => 'two'],
            ['type' => 'same', 'text' => ' word here.'],
        ], $result[1]['segments']);
    }

    public function test_formatting_only_changes_show_no_difference(): void
    {
        $result = $this->diff->body('<p>Plain words</p>', '<p><strong>Plain</strong> words</p>');

        $this->assertSame(['same'], array_column($result, 'status'));
    }

    public function test_very_long_rewrites_fall_back_to_whole_paragraphs(): void
    {
        $old = implode(' ', array_map(fn (int $i): string => "old{$i}", range(1, 400)));
        $new = implode(' ', array_map(fn (int $i): string => "new{$i}", range(1, 400)));

        $this->assertSame([
            ['type' => 'removed', 'text' => $old],
            ['type' => 'added', 'text' => $new],
        ], $this->diff->words($old, $new));
    }
}
