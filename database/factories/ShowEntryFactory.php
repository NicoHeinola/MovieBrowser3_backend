<?php

namespace Database\Factories;

use App\Enums\ShowEntryType;
use App\Models\Show\Show;
use App\Models\ShowEntry\ShowEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShowEntry>
 */
class ShowEntryFactory extends Factory
{
    protected $model = ShowEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'show_id' => Show::factory(),
            'type' => ShowEntryType::Season,
            'name' => 'Season '.fake()->unique()->numberBetween(1, 100000),
            'sort_order' => fake()->unique()->numberBetween(1, 100000),
        ];
    }

    public function tvSpecial(): static
    {
        return $this->state(fn (): array => [
            'type' => ShowEntryType::TvSpecial,
            'name' => fake()->unique()->sentence(3),
        ]);
    }

    public function movie(): static
    {
        return $this->state(fn (): array => [
            'type' => ShowEntryType::Movie,
            'name' => fake()->unique()->sentence(2),
        ]);
    }
}
