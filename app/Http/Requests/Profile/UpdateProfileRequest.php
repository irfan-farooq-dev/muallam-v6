<?php

namespace App\Http\Requests\Profile;

use App\Enums\Gender;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public const BIO_MAX = 1000;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::squish((string) $this->input('name')),
            'email' => Str::lower(trim((string) $this->input('email'))),
            'phone' => Str::squish((string) $this->input('phone')) ?: null,
            'bio' => trim((string) $this->input('bio')) ?: null,
            'gender' => $this->input('gender') ?: null,
            'is_bio_public' => $this->boolean('is_bio_public'),
            'show_online_status' => $this->boolean('show_online_status'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100', "regex:/^[\pL\pM][\pL\pM\s.'-]*$/u"],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($this->user())],
            'current_password' => [Rule::requiredIf(fn (): bool => $this->changesEmail()), 'nullable', 'string', 'current_password'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9][0-9\s\-()]{6,19}$/'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'bio' => ['nullable', 'string', 'max:'.self::BIO_MAX],
            'is_bio_public' => ['boolean'],
            'show_online_status' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'The name may only contain letters, spaces, dots, apostrophes and hyphens.',
            'email.unique' => 'Another account already uses this email.',
            'current_password.required' => 'Enter your current password to change your email.',
            'current_password.current_password' => 'The current password is incorrect.',
            'phone.regex' => 'Enter a phone number using digits, spaces, dashes or brackets, optionally starting with +.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'full name'];
    }

    public function changesEmail(): bool
    {
        return $this->input('email') !== $this->user()->email;
    }
}
