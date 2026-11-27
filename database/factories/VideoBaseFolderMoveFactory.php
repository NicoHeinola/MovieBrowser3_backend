<?php

namespace Database\Factories;

use App\Models\VideoBaseFolderMove\VideoBaseFolderMove;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VideoBaseFolderMove>
 */
class VideoBaseFolderMoveFactory extends Factory
{
    protected $model = VideoBaseFolderMove::class;

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
