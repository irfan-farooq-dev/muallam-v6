<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PostImage;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Images placed inside a post's body: uploaded from the editor before the post is saved,
 * linked to the post when it is saved, and removed once nothing uses them.
 */
class PostImageStore
{
    public const MAX_WIDTH = 1600;

    public function __construct(private ImageProcessor $images) {}

    public function upload(User $uploader, UploadedFile $file, ?string $alt = null): PostImage
    {
        $image = $this->images->limitWidth($this->images->load($file), self::MAX_WIDTH);
        $webp = $this->images->toWebp($image);

        $path = "posts/{$uploader->getKey()}/".Str::uuid().'.webp';
        Storage::disk('public')->put($path, $webp);

        return $uploader->postImages()->create([
            'path' => $path,
            'alt' => $alt,
            'width' => imagesx($image),
            'height' => imagesy($image),
            'size' => strlen($webp),
        ]);
    }

    public function url(PostImage $image): string
    {
        return Storage::disk('public')->url($image->path);
    }

    /**
     * After a post is saved: link the images its body uses (uploaded by $editor or the post's author)
     * and delete the post's images that the body no longer uses.
     */
    public function syncWithBody(Post $post, User $editor): void
    {
        $usedPaths = $this->pathsInBody((string) $post->body);

        PostImage::query()
            ->whereNull('post_id')
            ->whereIn('path', $usedPaths)
            ->whereIn('user_id', array_unique([$editor->getKey(), $post->user_id]))
            ->update(['post_id' => $post->getKey()]);

        $post->images()->whereNotIn('path', $usedPaths)->get()->each(fn (PostImage $image) => $this->delete($image));
    }

    public function deleteAllFor(Post $post): void
    {
        $post->images()->get()->each(fn (PostImage $image) => $this->delete($image));
    }

    /**
     * Uploads that never ended up in a saved post (e.g. the writer left the editor).
     */
    public function pruneUnattached(CarbonInterface $uploadedBefore): int
    {
        $stale = PostImage::query()->whereNull('post_id')->where('created_at', '<', $uploadedBefore)->get();
        $stale->each(fn (PostImage $image) => $this->delete($image));

        return $stale->count();
    }

    public function delete(PostImage $image): void
    {
        Storage::disk('public')->delete($image->path);
        $image->delete();
    }

    /**
     * Storage paths ("posts/7/uuid.webp") of this site's images used in the HTML.
     *
     * @return list<string>
     */
    public function pathsInBody(string $html): array
    {
        preg_match_all('/<img[^>]+src="([^"]+)"/i', $html, $matches);

        return collect($matches[1])
            ->map(fn (string $src): string => ltrim((string) parse_url(html_entity_decode($src), PHP_URL_PATH), '/'))
            ->filter(fn (string $path): bool => str_starts_with($path, 'storage/posts/'))
            ->map(fn (string $path): string => Str::after($path, 'storage/'))
            ->unique()
            ->values()
            ->all();
    }
}
