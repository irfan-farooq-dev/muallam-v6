<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserSlugHistory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UserSlugHistory>
 */
class UserSlugHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'slug' => Str::slug(fake()->name()).'-'.fake()->unique()->numberBetween(1, 999_999),
        ];
    }
}
