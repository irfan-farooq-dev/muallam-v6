<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Adding or editing a category.
 */
class SaveCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $name = Str::squish((string) $this->input('name'));

        $this->merge([
            'name' => $name,
            // Empty slug: build it from the name.
            'slug' => Str::slug((string) $this->input('slug')) ?: Str::slug($name),
            'description' => Str::squish((string) $this->input('description')) ?: null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($category)],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('categories', 'slug')->ignore($category)],
            'description' => ['nullable', 'string', 'max:300'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A category with this name already exists.',
            'slug.unique' => 'Another category already uses this link.',
            'slug.required' => 'The name must contain letters or numbers.',
        ];
    }

    /**
     * The default category ("General") must stay active and keep its link, because new posts start in it.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $category = $this->route('category');

                if (! $category instanceof Category || $category->slug !== config('muallam.default_category_slug')) {
                    return;
                }

                if (! $this->boolean('is_active')) {
                    $validator->errors()->add('is_active', 'The default category must stay active; new posts start in it.');
                }

                if ($this->input('slug') !== $category->slug) {
                    $validator->errors()->add('slug', 'The default category\'s link cannot change.');
                }
            },
        ];
    }
}
