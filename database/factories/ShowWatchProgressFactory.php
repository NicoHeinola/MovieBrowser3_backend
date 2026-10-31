<?php

namespace Database\Factories;

use App\Models\Episode\Episode;
use App\Models\ShowWatchProgress\ShowWatchProgress;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShowWatchProgress>
 */
class ShowWatchProgressFactory extends Factory
{
    protected $model = ShowWatchProgress::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'episode_id' => Episode::factory(),
            'show_entry_id' => fn (array $attributes): int => Episode::query()->findOrFail($attributes['episode_id'])->show_entry_id,
            'show_id' => fn (array $attributes): int => Episode::query()->findOrFail($attributes['episode_id'])->entry->show_id,
        ];
    }
}
