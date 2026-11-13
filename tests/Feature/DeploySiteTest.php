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

    public function test_a_new_release_first_clears_cached_settings_and_deploys_on_the_next_run(): void
    {
        $cachedConfig = storage_path('framework/testing-config-cache.php');
        file_put_contents($cachedConfig, '<?php return [];');
        $_SERVER['APP_CONFIG_CACHE'] = $_ENV['APP_CONFIG_CACHE'] = $cachedConfig;
        file_put_contents(DeploySite::releasePath(), "abc1234-20261108-120000\n");

        try {
            // As on the server: this run started from the cached settings file.
            $this->app->instance('config_loaded_from_cache', true);

            $this->artisan('site:deploy')
                ->expectsOutputToContain('New release abc1234-20261108-120000: cleared cached settings, deploying on the next run')
                ->doesntExpectOutputToContain('Deploying release')
                ->assertSuccessful();

            $this->assertFileDoesNotExist($cachedConfig);
            $this->assertFileDoesNotExist(DeploySite::markerPath());
        } finally {
            unset($_SERVER['APP_CONFIG_CACHE'], $_ENV['APP_CONFIG_CACHE']);
            @unlink($cachedConfig);
        }
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
