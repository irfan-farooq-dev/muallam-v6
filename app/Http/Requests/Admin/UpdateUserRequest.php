<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::squish((string) $this->input('name')),
            'email' => Str::lower(trim((string) $this->input('email'))),
            'is_active' => $this->boolean('is_active'),
            'email_verified' => $this->boolean('email_verified'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100', "regex:/^[\pL\pM][\pL\pM\s.'-]*$/u"],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($this->editedUser())],
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => ['boolean'],
            'email_verified' => ['boolean'],
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
        ];
    }

    /**
     * Admins cannot lock themselves out, and the site always keeps at least one active admin.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->editedUser();
                $losesAdmin = $user->isAdmin() && ($this->input('role') !== UserRole::Admin->value || ! $this->boolean('is_active'));

                if ($user->is($this->user()) && $this->input('role') !== UserRole::Admin->value) {
                    $validator->errors()->add('role', 'You cannot remove your own admin role.');
                }

                if ($user->is($this->user()) && ! $this->boolean('is_active')) {
                    $validator->errors()->add('is_active', 'You cannot deactivate your own account.');
                }

                if ($losesAdmin && User::where('role', UserRole::Admin)->where('is_active', true)->whereKeyNot($user->getKey())->doesntExist()) {
                    $validator->errors()->add('role', 'This is the only active admin. Make someone else an admin first.');
                }
            },
        ];
    }

    public function editedUser(): User
    {
        return $this->route('user');
    }
}
