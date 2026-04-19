<?php

namespace Database\Factories;

use App\Models\Episode\Episode;
use App\Models\ShowEntry\ShowEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Episode>
 */
class EpisodeFactory extends Factory
{
    protected $model = Episode::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->slug(3);

        return [
            'show_entry_id' => ShowEntry::factory(),
            'name' => $name,
            'filename' => $name.'.mkv',
            'sequence_number' => fake()->unique()->numberBetween(1, 1000),
        ];
    }
}
