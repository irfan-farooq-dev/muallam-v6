<?php

namespace App\Console\Commands;

use App\Services\PostImageStore;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('posts:prune-images {--hours=24 : Delete unused uploads older than this many hours}')]
#[Description('Delete editor image uploads that never became part of a saved post')]
class PrunePostImages extends Command
{
    public function handle(PostImageStore $images): int
    {
        $deleted = $images->pruneUnattached(now()->subHours((int) $this->option('hours')));

        $this->info("Deleted {$deleted} unused image(s).");

        return self::SUCCESS;
    }
}
