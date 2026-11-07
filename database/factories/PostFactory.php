<?php

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state: a draft.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim(fake()->sentence(6), '.');

        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 999_999),
            'summary' => fake()->sentence(20),
            'body' => '<p>'.implode('</p><p>', fake()->paragraphs(4)).'</p>',
            'featured_image' => null,
            'status' => PostStatus::Draft,
            'access_password' => null,
            'allow_comments' => true,
            'published_at' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PostStatus::Draft,
            'published_at' => null,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PostStatus::Published,
            'published_at' => fake()->dateTimeBetween('-6 months', '-1 hour'),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PostStatus::Published,
            'published_at' => fake()->dateTimeBetween('+1 day', '+1 month'),
        ]);
    }

    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PostStatus::Private,
        ]);
    }

    public function restricted(string $password = 'secret-pass'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PostStatus::Restricted,
            'access_password' => $password,
        ]);
    }

    public function withoutCategory(): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => null,
        ]);
    }
}
