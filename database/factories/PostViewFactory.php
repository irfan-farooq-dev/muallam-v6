<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\PostView;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostView>
 */
class PostViewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'user_id' => null,
            'country_code' => fake()->randomElement(['PK', 'US', 'GB', 'AE', null]),
            'viewed_at' => fake()->dateTimeBetween('-20 days'),
        ];
    }
}
