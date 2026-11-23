<?php

namespace Database\Factories;

use App\Models\Download\Download;
use App\Models\DownloadPick\DownloadPick;
use App\Models\Episode\Episode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DownloadPick>
 */
class DownloadPickFactory extends Factory
{
    protected $model = DownloadPick::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $index = $this->faker->unique()->numberBetween(0, 999);

        return [
            'download_id' => Download::factory(),
            'file_index' => $index,
            'path' => "Batch/Show - {$index}.mkv",
            'size_bytes' => 1_000_000_000,
            'episode_id' => Episode::factory(),
            'imported_at' => null,
        ];
    }
}
