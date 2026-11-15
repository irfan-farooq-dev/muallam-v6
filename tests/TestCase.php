<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /**
     * A paragraph that brings sample post content past the minimum length (SavePostRequest::BODY_MIN_CHARACTERS).
     */
    public const FILLER_PARAGRAPH = '<p>This extra paragraph makes the sample post long enough to save. It has a few plain sentences '
        .'about nothing in particular, so tests can focus on the parts they check. Gardens, books and travel are fine topics.</p>';

    /**
     * Sample post content followed by FILLER_PARAGRAPH, so it passes the minimum length.
     */
    protected static function longEnough(string $html): string
    {
        return $html.self::FILLER_PARAGRAPH;
    }

    /**
     * Tests never reach real outside services (such as the country lookup); fake them with Http::fake().
     */
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }
}
