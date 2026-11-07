<?php

namespace App\Services;

use App\Models\User;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Turns an uploaded photo into a square 320x320 WebP on the public disk.
 */
class AvatarProcessor
{
    public const SIZE = 320;

    private const QUALITY = 85;

    /**
     * Saves the new photo, deletes the previous one and returns the stored path.
     */
    public function replace(User $user, UploadedFile $file): string
    {
        $image = $this->load($file);
        $square = $this->cropToSquare($image);

        ob_start();
        imagewebp($square, null, self::QUALITY);
        $webp = (string) ob_get_clean();

        $path = "avatars/{$user->getKey()}/".Str::uuid().'.webp';
        Storage::disk('public')->put($path, $webp);

        $this->delete($user->avatar);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    private function load(UploadedFile $file): GdImage
    {
        $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if (! $image instanceof GdImage) {
            throw new RuntimeException('The uploaded file could not be read as an image.');
        }

        return $this->applyExifOrientation($image, $file);
    }

    /**
     * Phone photos are often stored sideways with an EXIF flag saying how to rotate them.
     */
    private function applyExifOrientation(GdImage $image, UploadedFile $file): GdImage
    {
        if (! function_exists('exif_read_data') || ! in_array($file->getMimeType(), ['image/jpeg', 'image/jpg'], true)) {
            return $image;
        }

        $orientation = @exif_read_data($file->getRealPath())['Orientation'] ?? 1;

        return match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }

    /**
     * Cuts the largest centred square and scales it to SIZE x SIZE.
     */
    private function cropToSquare(GdImage $image): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $side = min($width, $height);

        $square = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagealphablending($square, false);
        imagesavealpha($square, true);

        imagecopyresampled(
            $square, $image,
            0, 0,
            intdiv($width - $side, 2), intdiv($height - $side, 2),
            self::SIZE, self::SIZE,
            $side, $side,
        );

        return $square;
    }
}
