<?php

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Small GD helpers shared by avatars and post images: read an upload, fix phone-camera rotation,
 * crop or resize, and encode as WebP.
 */
class ImageProcessor
{
    public function load(UploadedFile $file): GdImage
    {
        $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if (! $image instanceof GdImage) {
            throw new RuntimeException('The uploaded file could not be read as an image.');
        }

        return $this->applyExifOrientation($image, $file);
    }

    /**
     * Largest centred square, scaled to $size x $size.
     */
    public function cropToSquare(GdImage $image, int $size): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $side = min($width, $height);

        $square = $this->canvas($size, $size);
        imagecopyresampled($square, $image, 0, 0, intdiv($width - $side, 2), intdiv($height - $side, 2), $size, $size, $side, $side);

        return $square;
    }

    /**
     * Scales down to at most $maxWidth wide, keeping the aspect ratio. Smaller images are left as they are.
     */
    public function limitWidth(GdImage $image, int $maxWidth): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width <= $maxWidth) {
            return $image;
        }

        $newHeight = (int) round($height * $maxWidth / $width);
        $resized = $this->canvas($maxWidth, $newHeight);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $maxWidth, $newHeight, $width, $height);

        return $resized;
    }

    public function toWebp(GdImage $image, int $quality = 82): string
    {
        ob_start();
        imagewebp($image, null, $quality);

        return (string) ob_get_clean();
    }

    private function canvas(int $width, int $height): GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        return $canvas;
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
}
