<?php

namespace Database\Factories;

use App\Models\VideoRelocation\VideoRelocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VideoRelocation>
 */
class VideoRelocationFactory extends Factory
{
    protected $model = VideoRelocation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'running' => true,
            'reverting' => false,
            'from_path' => '/media/old',
            'to_path' => '/media/new',
            'moved' => 0,
            'total' => 0,
        ];
    }

    public function finished(): self
    {
        return $this->state(['running' => null, 'finished_at' => now()]);
    }
}
