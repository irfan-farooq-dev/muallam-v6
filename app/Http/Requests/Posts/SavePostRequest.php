<?php

namespace App\Http\Requests\Posts;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Services\PostBodySanitizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Used for both creating and editing a post.
 */
class SavePostRequest extends FormRequest
{
    public const SUMMARY_MAX = 500;

    public const IMAGE_MAX_KILOBYTES = 4096;

    /**
     * Statuses offered in the editor. "Restricted" can be chosen once the sharing feature exists;
     * until then a post that is already restricted may keep that status.
     *
     * @return list<PostStatus>
     */
    public static function editableStatuses(?Post $post = null): array
    {
        $statuses = [PostStatus::Draft, PostStatus::Published, PostStatus::Private];

        if ($post?->status === PostStatus::Restricted) {
            $statuses[] = PostStatus::Restricted;
        }

        return $statuses;
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => Str::squish((string) $this->input('title')),
            'summary' => Str::squish((string) $this->input('summary')) ?: null,
            'category_id' => $this->input('category_id') ?: null,
            'body' => app(PostBodySanitizer::class)->clean($this->input('body')),
            'allow_comments' => $this->boolean('allow_comments'),
            'remove_featured_image' => $this->boolean('remove_featured_image'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'summary' => ['nullable', 'string', 'max:'.self::SUMMARY_MAX],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'body' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! app(PostBodySanitizer::class)->hasContent((string) $value)) {
                    $fail('Write something in the post before saving.');
                }
            }],
            'status' => ['required', Rule::in(array_map(fn (PostStatus $status): string => $status->value, self::editableStatuses($this->route('post'))))],
            'publish_option' => ['nullable', 'in:now,schedule'],
            'scheduled_at' => [
                Rule::requiredIf(fn (): bool => $this->input('status') === PostStatus::Published->value && $this->input('publish_option') === 'schedule'),
                'nullable', 'date', 'after:now',
            ],
            'allow_comments' => ['boolean'],
            'featured_image' => [
                'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::IMAGE_MAX_KILOBYTES,
                'dimensions:min_width=400,min_height=200,max_width=8000,max_height=8000',
            ],
            'remove_featured_image' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.exists' => 'Choose one of the listed categories.',
            'scheduled_at.required' => 'Choose when the post should go live.',
            'scheduled_at.after' => 'The scheduled time must be in the future.',
            'featured_image.image' => 'The featured image must be an image file.',
            'featured_image.mimes' => 'Use a JPG, PNG or WebP image.',
            'featured_image.max' => 'The featured image must be 4 MB or smaller.',
            'featured_image.dimensions' => 'The featured image must be at least 400 by 200 pixels and at most 8000 on each side.',
        ];
    }

    public function wantsSchedule(): bool
    {
        return $this->input('status') === PostStatus::Published->value && $this->input('publish_option') === 'schedule';
    }
}
