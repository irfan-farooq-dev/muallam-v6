<?php

namespace App\Http\Requests\Posts;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public const MAX_LENGTH = 2000;

    /**
     * Errors go into their own bag so they show under the comment form.
     *
     * @var string
     */
    protected $errorBag = 'comment';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            // Keep line breaks, but trim each line and collapse runs of blank lines.
            'body' => trim((string) preg_replace("/\n{3,}/", "\n\n", str_replace("\r\n", "\n", (string) $this->input('body')))),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:2', 'max:'.self::MAX_LENGTH],
            'parent_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => 'Write a comment before posting.',
            'body.min' => 'The comment is too short.',
            'body.max' => 'Comments can be up to '.self::MAX_LENGTH.' characters.',
        ];
    }
}
