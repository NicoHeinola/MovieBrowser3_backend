<?php

namespace Database\Factories;

use App\Models\Category\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * @return array<string, string|null>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'value' => fake()->unique()->slug(),
            'icon' => fake()->boolean() ? fake()->word() : null,
        ];
    }
}
