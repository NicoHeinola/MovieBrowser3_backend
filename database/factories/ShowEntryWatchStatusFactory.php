<?php

namespace Database\Factories;

use App\Enums\WatchStatus;
use App\Models\ShowEntry\ShowEntry;
use App\Models\ShowEntryWatchStatus\ShowEntryWatchStatus;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShowEntryWatchStatus>
 */
class ShowEntryWatchStatusFactory extends Factory
{
    protected $model = ShowEntryWatchStatus::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'show_entry_id' => ShowEntry::factory(),
            'user_id' => User::factory(),
            'status' => fake()->randomElement(WatchStatus::cases()),
        ];
    }
}
