<?php

namespace Database\Seeders;

use App\Enums\ShowEntryType;
use App\Models\Episode\Episode;
use App\Models\ShowEntry\ShowEntry;
use Illuminate\Database\Seeder;

class EpisodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ShowEntry::all()->each(function (ShowEntry $entry) {
            match ($entry->type) {
                ShowEntryType::Season => $this->seedSeasonEpisodes($entry, rand(8, 24)),
                ShowEntryType::Movie => $this->seedMovieEpisode($entry),
                ShowEntryType::TvSpecial => $this->seedTvSpecialEpisodes($entry, rand(1, 3)),
            };
        });
    }

    private function seedSeasonEpisodes(ShowEntry $entry, int $episodeCount): void
    {
        for ($sequenceNumber = 1; $sequenceNumber <= $episodeCount; $sequenceNumber++) {
            $episodeName = fake()->unique()->sentence(rand(1, 4));

            Episode::factory()->for($entry, 'entry')->create([
                'name' => sprintf('Episode %02d - %s', $sequenceNumber, rtrim($episodeName, '. ')),
                'filename' => sprintf('show-entry-%d-%02d.mkv', $entry->id, $sequenceNumber),
                'sequence_number' => $sequenceNumber,
            ]);
        }
    }

    private function seedMovieEpisode(ShowEntry $entry): void
    {
        $movieName = fake()->unique()->sentence(rand(2, 5));

        Episode::factory()->for($entry, 'entry')->create([
            'name' => rtrim($movieName, '. '),
            'filename' => sprintf('show-entry-%d-feature.mkv', $entry->id),
            'sequence_number' => 1,
        ]);
    }

    private function seedTvSpecialEpisodes(ShowEntry $entry, int $episodeCount): void
    {
        for ($sequenceNumber = 1; $sequenceNumber <= $episodeCount; $sequenceNumber++) {
            $specialName = fake()->unique()->sentence(rand(2, 6));

            Episode::factory()->for($entry, 'entry')->create([
                'name' => rtrim($specialName, '. '),
                'filename' => sprintf('show-entry-%d-special-%02d.mkv', $entry->id, $sequenceNumber),
                'sequence_number' => $sequenceNumber,
            ]);
        }
    }
}
