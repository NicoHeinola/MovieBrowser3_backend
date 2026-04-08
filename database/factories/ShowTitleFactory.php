<?php

namespace Database\Factories;

use App\Models\Show\Show;
use App\Models\ShowTitle\ShowTitle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShowTitle>
 */
class ShowTitleFactory extends Factory
{
    protected $model = ShowTitle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'show_id' => Show::factory(),
            'title' => fake()->unique()->sentence(3),
            'is_primary' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (): array => [
            'is_primary' => true,
        ]);
    }
}
