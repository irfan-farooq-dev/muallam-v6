<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The real category list. Safe to run more than once, including on the live site:
 *
 *     php artisan db:seed --class=CategorySeeder
 *
 * Existing categories (matched by slug) get their name, description and order updated; nothing is deleted.
 */
class CategorySeeder extends Seeder
{
    /**
     * Shown in this order in menus.
     *
     * @var list<array{name: string, description: string|null}>
     */
    public const CATEGORIES = [
        ['name' => 'Religion & Philosophy', 'description' => null],
        ['name' => 'Politics & Society', 'description' => null],
        ['name' => 'Culture, Art & Travel', 'description' => null],
        ['name' => 'Entertainment & Sports', 'description' => null],
        ['name' => 'Health & Lifestyle', 'description' => null],
        ['name' => 'Business & Finance', 'description' => null],
        ['name' => 'Science & Technology', 'description' => null],
        ['name' => 'General', 'description' => 'Posts that do not fit another category.'],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $position => $category) {
            Category::updateOrCreate(
                ['slug' => Str::slug($category['name'])],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'sort_order' => $position,
                ],
            );
        }
    }
}
