<?php

namespace Database\Factories;

use App\Enums\ShowWatchStatus;
use App\Models\ShowEntry\ShowEntry;
use App\Models\ShowEntryStatus\ShowEntryStatus;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShowEntryStatus>
 */
class ShowEntryStatusFactory extends Factory
{
    protected $model = ShowEntryStatus::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'show_entry_id' => ShowEntry::factory(),
            'user_id' => User::factory(),
            'status' => fake()->randomElement(ShowWatchStatus::cases()),
        ];
    }
}
