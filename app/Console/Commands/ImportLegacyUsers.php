<?php

namespace App\Console\Commands;

use App\Services\LegacyUserImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('legacy:import-users {--dry-run : Show what would happen without saving anything} {--connection=legacy : Database connection holding the old site\'s tables}')]
#[Description('Copy member accounts from the old muallam.com database (passwords keep working)')]
class ImportLegacyUsers extends Command
{
    public function handle(LegacyUserImporter $importer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $report = $importer->import(DB::connection((string) $this->option('connection')), $dryRun);

        $this->table(
            ['Old id', 'Name', 'Email', 'Result', 'Note'],
            array_map(fn (array $row): array => [$row['old_id'], $row['name'], $row['email'], $row['result'], $row['note']], $report),
        );

        $imported = count(array_filter($report, fn (array $row): bool => $row['result'] === 'imported'));
        $skipped = count($report) - $imported;

        $this->info(($dryRun ? 'Dry run: would import' : 'Imported')." {$imported} user(s), skipped {$skipped}.");

        return self::SUCCESS;
    }
}
