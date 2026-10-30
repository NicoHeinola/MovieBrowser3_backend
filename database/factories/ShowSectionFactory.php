<?php

namespace Database\Factories;

use App\Models\Category\Category;
use App\Models\ShowSection\ShowSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShowSection>
 */
class ShowSectionFactory extends Factory
{
    protected $model = ShowSection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'description' => fake()->boolean() ? fake()->sentence() : null,
            'sort_order' => fake()->unique()->numberBetween(0, 100000),
        ];
    }
}
