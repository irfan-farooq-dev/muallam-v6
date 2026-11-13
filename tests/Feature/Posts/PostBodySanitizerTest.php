<?php

namespace Tests\Feature\Posts;

use App\Services\PostBodySanitizer;
use Tests\TestCase;

class PostBodySanitizerTest extends TestCase
{
    public function test_text_pasted_with_colour_wrappers_keeps_its_words(): void
    {
        $html = '<h2><span style="color: rgb(13, 13, 13);">What is an RPC?</span></h2>'
            .'<p><span style="background-color: rgb(255, 255, 255);">At its core, </span><strong>RPC</strong><span> is a protocol.</span></p>'
            .'<ul><li data-list="bullet"><span>The CEO doesn’t speak Arabic.</span></li></ul>'
            .'<div><p><font color="red">Inside a div.</font></p></div>';

        $this->assertSame(
            '<h2>What is an RPC?</h2><p>At its core, <strong>RPC</strong> is a protocol.</p>'
            .'<ul><li data-list="bullet">The CEO doesn’t speak Arabic.</li></ul><p>Inside a div.</p>',
            app(PostBodySanitizer::class)->clean($html),
        );
    }

    public function test_dangerous_tags_are_still_removed_with_their_contents(): void
    {
        $html = '<p>Safe<script>alert(1)</script><style>p { display: none }</style><iframe src="https://evil.test">frame</iframe>'
            .'<span onclick="alert(1)"> text</span></p>';

        $this->assertSame('<p>Safe text</p>', app(PostBodySanitizer::class)->clean($html));
    }
}
