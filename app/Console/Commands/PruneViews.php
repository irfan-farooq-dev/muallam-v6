<?php

namespace App\Console\Commands;

use App\Models\PostView;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('views:prune {--days=365 : Delete logged views older than this many days}')]
#[Description('Delete old rows from the post view log (the view counts on posts are not changed)')]
class PruneViews extends Command
{
    public function handle(): int
    {
        $deleted = PostView::query()
            ->where('viewed_at', '<', now()->subDays((int) $this->option('days')))
            ->delete();

        $this->info("Deleted {$deleted} old logged view(s).");

        return self::SUCCESS;
    }
}
