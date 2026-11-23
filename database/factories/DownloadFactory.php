<?php

namespace Database\Factories;

use App\Enums\DownloadStatus;
use App\Models\Download\Download;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Download>
 */
class DownloadFactory extends Factory
{
    protected $model = Download::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hash = $this->faker->unique()->sha1();

        return [
            'hash' => $hash,
            'name' => '[Group] '.$this->faker->words(3, true).' (1080p)',
            'magnet_url' => "magnet:?xt=urn:btih:{$hash}",
            'show_id' => null,
            'status' => DownloadStatus::WaitingForFiles,
            'error_code' => null,
        ];
    }
}
