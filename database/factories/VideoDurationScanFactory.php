<?php

namespace Database\Factories;

use App\Models\VideoDurationScan\VideoDurationScan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VideoDurationScan>
 */
class VideoDurationScanFactory extends Factory
{
    protected $model = VideoDurationScan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'running' => true,
            'read' => 0,
            'total' => 0,
        ];
    }
}
