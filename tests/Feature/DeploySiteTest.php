<?php

namespace Tests\Feature;

use App\Console\Commands\DeploySite;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeploySiteTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        @unlink(DeploySite::releasePath());
        @unlink(DeploySite::markerPath());

        parent::tearDown();
    }

    public function test_without_a_release_file_nothing_is_deployed(): void
    {
        @unlink(DeploySite::releasePath());

        $this->artisan('site:deploy')
            ->expectsOutputToContain('No RELEASE file')
            ->doesntExpectOutputToContain('Deploying')
            ->assertSuccessful();
    }

    public function test_a_release_that_was_already_deployed_is_skipped_quietly(): void
    {
        file_put_contents(DeploySite::releasePath(), "abc1234-20261108-120000\n");
        file_put_contents(DeploySite::markerPath(), 'abc1234-20261108-120000');

        $this->artisan('site:deploy')
            ->doesntExpectOutputToContain('Deploying')
            ->assertSuccessful();
    }

    public function test_scheduler_checks_for_new_releases_every_minute_with_file_locks(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'site:deploy'));

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }
}
