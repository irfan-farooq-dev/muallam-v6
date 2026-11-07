<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores a post's featured image as a WebP at most 1600px wide.
 */
class FeaturedImageProcessor
{
    public const MAX_WIDTH = 1600;

    public function __construct(private ImageProcessor $images) {}

    public function store(User $author, UploadedFile $file): string
    {
        $image = $this->images->limitWidth($this->images->load($file), self::MAX_WIDTH);

        $path = "posts/{$author->getKey()}/featured/".Str::uuid().'.webp';
        Storage::disk('public')->put($path, $this->images->toWebp($image));

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
