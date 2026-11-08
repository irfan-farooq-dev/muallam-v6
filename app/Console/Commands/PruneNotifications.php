<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

#[Signature('notifications:prune {--days=90 : Delete notifications read more than this many days ago}')]
#[Description('Delete old notifications that have already been read')]
class PruneNotifications extends Command
{
    public function handle(): int
    {
        $deleted = DatabaseNotification::query()
            ->whereNotNull('read_at')
            ->where('read_at', '<', now()->subDays((int) $this->option('days')))
            ->delete();

        $this->info("Deleted {$deleted} old notification(s).");

        return self::SUCCESS;
    }
}
