<?php

namespace Database\Factories;

use App\Models\VideoFileCheck\VideoFileCheck;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VideoFileCheck>
 */
class VideoFileCheckFactory extends Factory
{
    protected $model = VideoFileCheck::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'running' => true,
            'checked' => 0,
            'total' => 0,
        ];
    }
}
