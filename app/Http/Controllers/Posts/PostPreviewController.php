<?php

namespace App\Http\Controllers\Posts;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Services\PostBodySanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Shows the editor's current, unsaved content the way readers will see it. Nothing is saved.
 */
class PostPreviewController extends Controller
{
    private const MAX_PREVIEW_IMAGE_KILOBYTES = 4096;

    public function __invoke(Request $request, PostBodySanitizer $sanitizer): View
    {
        $existing = $request->filled('preview_post') ? Post::where('slug', $request->input('preview_post'))->first() : null;

        if ($existing) {
            Gate::authorize('update', $existing);
        }

        $post = new Post([
            'title' => Str::squish((string) $request->input('title')) ?: 'Untitled post',
            'summary' => Str::squish((string) $request->input('summary')) ?: null,
            'body' => $sanitizer->clean($request->input('body')),
            'status' => PostStatus::Published,
            'published_at' => $this->previewPublishTime($request),
        ]);
        $post->setRelation('user', $request->user());
        $post->setRelation('category', Category::query()->active()->find($request->input('category_id')));

        return view('posts.preview', [
            'post' => $post,
            'imageUrl' => $this->previewImageUrl($request, $existing),
            'isScheduled' => $request->boolean('schedule') && $request->filled('scheduled_at'),
        ]);
    }

    private function previewPublishTime(Request $request): Carbon
    {
        if ($request->boolean('schedule') && $request->filled('scheduled_at')) {
            return rescue(fn (): Carbon => Carbon::parse($request->input('scheduled_at')), now(), report: false);
        }

        return now();
    }

    /**
     * A newly chosen image is shown inline (not stored); otherwise the saved one, unless it is being removed.
     */
    private function previewImageUrl(Request $request, ?Post $existing): ?string
    {
        $file = $request->file('featured_image');

        if ($file?->isValid() && str_starts_with((string) $file->getMimeType(), 'image/') && $file->getSize() <= self::MAX_PREVIEW_IMAGE_KILOBYTES * 1024) {
            return 'data:'.$file->getMimeType().';base64,'.base64_encode((string) file_get_contents($file->getRealPath()));
        }

        if ($existing && ! $request->boolean('remove_featured_image')) {
            return $existing->featuredImageUrl();
        }

        return null;
    }
}
