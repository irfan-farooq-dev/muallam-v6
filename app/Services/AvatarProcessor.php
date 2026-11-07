<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Turns an uploaded photo into a square 320x320 WebP on the public disk.
 */
class AvatarProcessor
{
    public const SIZE = 320;

    public function __construct(private ImageProcessor $images) {}

    /**
     * Saves the new photo, deletes the previous one and returns the stored path.
     */
    public function replace(User $user, UploadedFile $file): string
    {
        $square = $this->images->cropToSquare($this->images->load($file), self::SIZE);

        $path = "avatars/{$user->getKey()}/".Str::uuid().'.webp';
        Storage::disk('public')->put($path, $this->images->toWebp($square, 85));

        $this->delete($user->avatar);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
