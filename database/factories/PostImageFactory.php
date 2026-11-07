<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\PostImage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PostImage>
 */
class PostImageFactory extends Factory
{
    /**
     * Define the model's default state: an image attached to a post.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'post_id' => Post::factory(),
            'path' => fn (array $attributes): string => 'posts/'.$attributes['user_id'].'/'.Str::uuid().'.webp',
            'alt' => fake()->sentence(4),
            'width' => 1600,
            'height' => 900,
            'size' => fake()->numberBetween(20_000, 400_000),
        ];
    }

    /**
     * Uploaded from the editor but not yet saved with a post.
     */
    public function unattached(): static
    {
        return $this->state(fn (array $attributes) => [
            'post_id' => null,
        ]);
    }
}
