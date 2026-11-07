<?php

namespace App\Http\Requests\Posts;

use App\Enums\PostStatus;
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

    public const ACCESS_PASSWORD_MIN = 6;

    /**
     * Statuses the editor can produce.
     *
     * @return list<PostStatus>
     */
    public static function editableStatuses(): array
    {
        return [PostStatus::Draft, PostStatus::Published, PostStatus::Private, PostStatus::Restricted];
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * The editor sends a button ("action": draft or publish) and a visibility (public or private)
     * instead of a raw status. They are turned into a status here; the status rules below check the result.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->statusFromForm(),
            'publish_option' => $this->boolean('schedule') ? 'schedule' : 'now',
            'title' => Str::squish((string) $this->input('title')),
            'summary' => Str::squish((string) $this->input('summary')) ?: null,
            'category_id' => $this->input('category_id') ?: null,
            'body' => app(PostBodySanitizer::class)->clean($this->input('body')),
            'allow_comments' => $this->boolean('allow_comments'),
            'remove_featured_image' => $this->boolean('remove_featured_image'),
        ]);

        // A schedule time only matters when publishing publicly with "Schedule for later" on.
        if (! $this->wantsSchedule()) {
            $this->merge(['scheduled_at' => null]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'summary' => ['nullable', 'string', 'max:'.self::SUMMARY_MAX],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'action' => ['required', 'in:draft,publish'],
            'visibility' => ['required', Rule::in($this->allowedVisibilities())],
            'body' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! app(PostBodySanitizer::class)->hasContent((string) $value)) {
                    $fail('Write something in the post before saving.');
                }
            }],
            'status' => ['required', Rule::in(array_map(fn (PostStatus $status): string => $status->value, self::editableStatuses()))],
            // Invited readers need this password to open a restricted post. Left empty on an existing restricted post, the old one is kept.
            'access_password' => [
                Rule::requiredIf(fn (): bool => $this->input('status') === PostStatus::Restricted->value && ! $this->route('post')?->access_password),
                'nullable', 'string', 'min:'.self::ACCESS_PASSWORD_MIN, 'max:72',
            ],
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
            'category_id.required' => 'Choose a category.',
            'access_password.required' => 'Set a password that invited readers will need to open the post.',
            'access_password.min' => 'The post password must be at least '.self::ACCESS_PASSWORD_MIN.' characters.',
            'category_id.exists' => 'Choose one of the listed categories.',
            'scheduled_at.required' => 'Choose when the post should go live.',
            'scheduled_at.after' => 'The scheduled time must be in the future.',
            'featured_image.image' => 'The featured image must be an image file.',
            'featured_image.mimes' => 'Use a JPG, PNG or WebP image.',
            'featured_image.max' => 'The featured image must be 4 MB or smaller.',
            'featured_image.dimensions' => 'The featured image must be at least 400 by 200 pixels and at most 8000 on each side.',
        ];
    }

    /**
     * @return list<string>
     */
    private function allowedVisibilities(): array
    {
        return ['public', 'private', 'restricted'];
    }

    private function statusFromForm(): ?string
    {
        if ($this->input('action') === 'draft') {
            return PostStatus::Draft->value;
        }

        if ($this->input('action') !== 'publish') {
            return null;
        }

        return match ($this->input('visibility')) {
            'private' => PostStatus::Private->value,
            'restricted' => PostStatus::Restricted->value,
            default => PostStatus::Published->value,
        };
    }

    public function wantsSchedule(): bool
    {
        return $this->input('status') === PostStatus::Published->value && $this->input('publish_option') === 'schedule';
    }
}
