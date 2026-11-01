<?php

namespace Database\Factories;

use App\Models\Website\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Website>
 */
class WebsiteFactory extends Factory
{
    protected $model = Website::class;

    /**
     * @return array<string, string|bool|null>
     */
    public function definition(): array
    {
        return [
            'url' => fake()->unique()->url(),
            'title' => fake()->words(3, true),
            'icon' => null,
            'description' => fake()->boolean() ? fake()->sentence() : null,
            'is_favorite' => false,
        ];
    }

    public function favorite(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_favorite' => true,
        ]);
    }
}
