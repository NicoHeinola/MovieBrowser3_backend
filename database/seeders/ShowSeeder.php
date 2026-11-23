<?php

namespace Database\Seeders;

use App\Actions\ShowLink\CreateShowLinkAction;
use App\Dtos\ShowLink\CreateShowLinkData;
use App\Enums\ShowEntryType;
use App\Enums\ShowLinkType;
use App\Models\Category\Category;
use App\Models\Show\Show;
use App\Models\ShowTitle\ShowTitle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ShowSeeder extends Seeder
{
    /**
     * Creates the catalog's shows that aren't there yet, by primary title, and links them to each other. Shows that
     * already exist are left alone.
     */
    public function run(ShowCatalog $catalog): void
    {
        $existingTitles = ShowTitle::query()->where('is_primary', true)->pluck('title');
        $newShows = $catalog->shows()->except($existingTitles);
        $categoryIds = Category::query()->pluck('id', 'value');

        DB::transaction(function () use ($newShows, $categoryIds): void {
            $shows = $newShows->map(fn (array $data): Show => $this->seedShow($data, $categoryIds->only($data['categories'])->values()->all()));

            $newShows->each(fn (array $data, string $title) => $this->seedLinks($shows[$title], $data['links'], $shows));
        });
    }

    /**
     * @param  array<string, mixed>  $data  A show from the catalog
     * @param  list<int>  $categoryIds
     */
    private function seedShow(array $data, array $categoryIds): Show
    {
        $show = Show::query()->create([
            'banner_url' => $data['banner_url'],
            'card_image_url' => $data['card_image_url'],
            'preview_url' => $data['preview_url'],
            'description' => $data['description'],
        ]);

        $show->categories()->attach($categoryIds);

        foreach ($data['titles'] as $index => $title) {
            $show->titles()->create(['title' => $title, 'is_primary' => $index === 0]);
        }

        $this->seedEntries($show, $data['titles'][0], $data['entries']);

        return $show;
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     */
    private function seedEntries(Show $show, string $showTitle, array $entries): void
    {
        $seasonNumber = 0;

        foreach ($entries as $index => $data) {
            $type = ShowEntryType::from($data['type']);
            $seasonNumber += $type === ShowEntryType::Season ? 1 : 0;

            $entry = $show->entries()->create([
                'type' => $type,
                'name' => $data['name'],
                'url' => $data['url'],
                'sort_order' => $index + 1,
            ]);

            $entry->episodes()->createMany(array_map(fn (array $episode, int $episodeIndex): array => [
                'name' => $episode['name'],
                'filename' => $type === ShowEntryType::Season
                    ? sprintf('%s - S%02dE%02d.mkv', $showTitle, $seasonNumber, $episodeIndex + 1)
                    : "{$showTitle} - {$episode['name']}.mkv",
                'video_duration_seconds' => $episode['duration_seconds'],
                'sequence_number' => $episodeIndex + 1,
            ], $data['episodes'], array_keys($data['episodes'])));
        }
    }

    /**
     * Links to shows that weren't created in this run are skipped, so an existing show never gains a link.
     *
     * @param  list<array{type: string, title: string}>  $links
     * @param  Collection<string, Show>  $shows  The shows created in this run, by primary title
     */
    private function seedLinks(Show $show, array $links, Collection $shows): void
    {
        foreach ($links as $link) {
            if (!$shows->has($link['title'])) {
                continue;
            }

            // Also creates the link back
            CreateShowLinkAction::run($show, new CreateShowLinkData(
                targetShowId: $shows[$link['title']]->id,
                type: ShowLinkType::from($link['type']),
            ));
        }
    }
}
