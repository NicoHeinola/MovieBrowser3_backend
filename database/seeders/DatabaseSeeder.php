<?php

namespace Database\Seeders;

use App\Enums\ShowEntryType;
use App\Enums\ShowLinkType;
use App\Models\Episode\Episode;
use App\Models\Show\Show;
use App\Models\ShowEntry\ShowEntry;
use App\Models\ShowLink\ShowLink;
use App\Models\ShowTitle\ShowTitle;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $shows = Show::factory(30)
            ->has(ShowTitle::factory()->primary(), 'titles')
            ->has(ShowTitle::factory(rand(1, 3)), 'titles')
            ->create();

        $shows->each(function (Show $show, int $index): void {
            $types = collect();
            $seasonCounter = 0;

            // Give every show at least one "main" entry (Season or Movie)
            if (rand(0, 100) < 80) {
                $types->push(ShowEntryType::Season);
            } else {
                $types->push(ShowEntryType::Movie);
            }

            // Maybe add more seasons
            if ($types->contains(ShowEntryType::Season) && rand(0, 100) < 40) {
                $types->push(ShowEntryType::Season);
                if (rand(0, 100) < 30) {
                    $types->push(ShowEntryType::Season);
                }
            }

            // Maybe add a movie if it doesn't have one
            if (!$types->contains(ShowEntryType::Movie) && rand(0, 100) < 20) {
                $types->push(ShowEntryType::Movie);
            }

            // Maybe add TV specials
            if (rand(0, 100) < 30) {
                $types->push(ShowEntryType::TvSpecial);
            }

            $types->shuffle()->each(function (ShowEntryType $type, int $sortOrder) use ($show, &$seasonCounter): void {
                $entry = ShowEntry::factory()->for($show)->create([
                    'type' => $type,
                    'name' => match ($type) {
                        ShowEntryType::Season => 'Season '.++$seasonCounter,
                        ShowEntryType::Movie => fake()->sentence(rand(2, 3)),
                        ShowEntryType::TvSpecial => 'Special: '.fake()->sentence(rand(3, 4)),
                    },
                    'sort_order' => $sortOrder + 1,
                ]);

                match ($type) {
                    ShowEntryType::Season => $this->seedSeasonEpisodes($entry, rand(8, 24)),
                    ShowEntryType::Movie => $this->seedMovieEpisode($entry),
                    ShowEntryType::TvSpecial => $this->seedTvSpecialEpisodes($entry, rand(1, 3)),
                };
            });
        });

        $this->seedShowLinks($shows);
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

    /**
     * @param  Collection<int, Show>  $shows
     */
    private function seedShowLinks(Collection $shows): void
    {
        $orderedShows = $shows->values();

        for ($index = 0; $index < $orderedShows->count() - 1; $index++) {
            $sourceShow = $orderedShows[$index];
            $targetShow = $orderedShows[$index + 1];

            ShowLink::factory()->create([
                'source_show_id' => $sourceShow->id,
                'target_show_id' => $targetShow->id,
                'type' => ShowLinkType::Sequel,
            ]);

            ShowLink::factory()->prequel()->create([
                'source_show_id' => $targetShow->id,
                'target_show_id' => $sourceShow->id,
            ]);
        }
    }
}
