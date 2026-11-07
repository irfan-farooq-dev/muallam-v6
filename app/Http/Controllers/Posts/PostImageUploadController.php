<?php

namespace App\Http\Controllers\Posts;

use App\Http\Controllers\Controller;
use App\Services\PostImageStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Called by the editor when an image is added (toolbar button, paste or drag-and-drop).
 */
class PostImageUploadController extends Controller
{
    public const MAX_KILOBYTES = 5120;

    public function __invoke(Request $request, PostImageStore $images): JsonResponse
    {
        $request->validate([
            'image' => [
                'required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_KILOBYTES,
                'dimensions:min_width=50,min_height=50,max_width=8000,max_height=8000',
            ],
            'alt' => ['nullable', 'string', 'max:255'],
        ], [
            'image.image' => 'The file must be an image.',
            'image.mimes' => 'Use a JPG, PNG or WebP image.',
            'image.max' => 'Images must be 5 MB or smaller.',
            'image.dimensions' => 'Images must be between 50 and 8000 pixels on each side.',
        ]);

        $image = $images->upload($request->user(), $request->file('image'), $request->input('alt'));

        return response()->json([
            'url' => $images->url($image),
            'width' => $image->width,
            'height' => $image->height,
            'alt' => $image->alt,
        ], 201);
    }
}
