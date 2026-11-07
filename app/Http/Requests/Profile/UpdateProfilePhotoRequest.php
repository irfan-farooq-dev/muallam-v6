<?php

namespace App\Http\Requests\Profile;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfilePhotoRequest extends FormRequest
{
    public const MAX_KILOBYTES = 2048;

    /**
     * @var string
     */
    protected $errorBag = 'updatePhoto';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'photo' => [
                'required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_KILOBYTES,
                'dimensions:min_width=100,min_height=100,max_width=6000,max_height=6000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.required' => 'Choose a photo to upload.',
            'photo.image' => 'The file must be an image.',
            'photo.mimes' => 'Use a JPG, PNG or WebP image.',
            'photo.max' => 'The photo must be 2 MB or smaller.',
            'photo.dimensions' => 'The photo must be between 100 and 6000 pixels on each side.',
        ];
    }
}
