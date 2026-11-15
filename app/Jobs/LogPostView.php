<?php

namespace App\Jobs;

use App\Models\PostView;
use App\Services\CountryLookup;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

/**
 * Logs one post view with the visitor's country. Dispatched "after response" from PostController, so the reader
 * already has the page while the country is looked up. The IP is only used for the lookup and never stored.
 */
class LogPostView
{
    use Queueable;

    public function __construct(
        public int $postId,
        public ?int $userId,
        public ?string $ip,
        public Carbon $viewedAt,
    ) {}

    public function handle(CountryLookup $countries): void
    {
        PostView::create([
            'post_id' => $this->postId,
            'user_id' => $this->userId,
            'country_code' => $countries->country($this->ip),
            'viewed_at' => $this->viewedAt,
        ]);
    }
}
