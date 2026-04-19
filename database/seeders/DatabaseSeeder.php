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
        $shows = Show::factory(20)
            ->has(ShowTitle::factory()->primary(), 'titles')
            ->has(ShowTitle::factory(2), 'titles')
            ->create();

        $shows->each(function (Show $show, int $index): void {
            $seasonEntry = ShowEntry::factory()->for($show)->create([
                'type' => ShowEntryType::Season,
                'name' => 'Season 1',
                'sort_order' => 1,
            ]);
            $this->seedSeasonEpisodes($seasonEntry, [10, 12, 13][$index % 3]);

            $movieEntry = ShowEntry::factory()->for($show)->movie()->create([
                'name' => 'Movie',
                'sort_order' => 2,
            ]);
            $this->seedMovieEpisode($movieEntry);

            $tvSpecialEntry = ShowEntry::factory()->for($show)->tvSpecial()->create([
                'name' => 'TV Special',
                'sort_order' => 3,
            ]);
            $this->seedTvSpecialEpisodes($tvSpecialEntry, $index % 4 === 0 ? 2 : 1);
        });

        $this->seedShowLinks($shows);
    }

    private function seedSeasonEpisodes(ShowEntry $entry, int $episodeCount): void
    {
        for ($sequenceNumber = 1; $sequenceNumber <= $episodeCount; $sequenceNumber++) {
            Episode::factory()->for($entry, 'entry')->create([
                'name' => sprintf('Episode %02d', $sequenceNumber),
                'filename' => sprintf('show-entry-%d-%02d.mkv', $entry->id, $sequenceNumber),
                'sequence_number' => $sequenceNumber,
            ]);
        }
    }

    private function seedMovieEpisode(ShowEntry $entry): void
    {
        Episode::factory()->for($entry, 'entry')->create([
            'name' => 'Feature Presentation',
            'filename' => sprintf('show-entry-%d-feature.mkv', $entry->id),
            'sequence_number' => 1,
        ]);
    }

    private function seedTvSpecialEpisodes(ShowEntry $entry, int $episodeCount): void
    {
        for ($sequenceNumber = 1; $sequenceNumber <= $episodeCount; $sequenceNumber++) {
            Episode::factory()->for($entry, 'entry')->create([
                'name' => $episodeCount === 1
                    ? 'Broadcast Special'
                    : sprintf('Broadcast Special Part %d', $sequenceNumber),
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
