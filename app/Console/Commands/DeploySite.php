<?php

namespace App\Console\Commands;

use Database\Seeders\CategorySeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Finishes installing an uploaded release on hosting without a terminal. The scheduler runs it every minute;
 * it only does work when the RELEASE file (written by site:package) differs from the last finished deploy.
 * A new release first clears the cached settings, so the steps (one minute later) always use the current .env.
 */
#[Signature('site:deploy {--force : Run even if this release was already deployed}')]
#[Description('Apply a newly uploaded release: migrate, seed categories, import old members, link storage, cache')]
class DeploySite extends Command
{
    public function handle(): int
    {
        $release = $this->currentRelease();

        if ($release === null) {
            $this->line('No RELEASE file: this is not an uploaded package, nothing to deploy.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && $this->lastDeployedRelease() === $release) {
            return self::SUCCESS;
        }

        // Settings cached by the previous deploy would hide .env changes (a new database, the import switch) from
        // every step below. Clear them and deploy on the next run, which reads .env afresh.
        if ($this->laravel->configurationIsCached()) {
            $this->info('['.now()->toDateTimeString()."] New release {$release}: cleared cached settings, deploying on the next run");
            $this->call('optimize:clear');

            return self::SUCCESS;
        }

        $this->info('['.now()->toDateTimeString()."] Deploying release {$release}");

        $steps = [
            ['migrate', ['--force' => true]],
            ['db:seed', ['--class' => CategorySeeder::class, '--force' => true]],
            ...(config('database.connections.legacy.database') ? [['legacy:import-users', []]] : []),
            ['storage:link', []],
            ['optimize', []],
        ];

        foreach ($steps as [$command, $arguments]) {
            $this->line("> php artisan {$command}");

            if ($this->call($command, $arguments) !== self::SUCCESS) {
                $this->error("Step \"{$command}\" failed; the deploy will be tried again on the next run.");

                return self::FAILURE;
            }
        }

        file_put_contents(self::markerPath(), $release);
        $this->info("Release {$release} deployed.");

        return self::SUCCESS;
    }

    public static function releasePath(): string
    {
        return base_path('RELEASE');
    }

    public static function markerPath(): string
    {
        return storage_path('app/deployed-release');
    }

    private function currentRelease(): ?string
    {
        return is_file(self::releasePath()) ? (trim((string) file_get_contents(self::releasePath())) ?: null) : null;
    }

    private function lastDeployedRelease(): ?string
    {
        return is_file(self::markerPath()) ? trim((string) file_get_contents(self::markerPath())) : null;
    }
}
