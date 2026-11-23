<?php

use App\Enums\ShowLinkType;
use App\Models\Category\Category;
use App\Models\Episode\Episode;
use App\Models\Show\Show;
use App\Models\ShowLink\ShowLink;
use App\Models\ShowTitle\ShowTitle;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ShowCatalog;

use function Pest\Laravel\seed;

it('creates every show of the catalog with its titles, categories, entries and episodes', function (): void {
    seed(DatabaseSeeder::class);

    $catalog = app(ShowCatalog::class)->shows();
    $shows = Show::query()->with(['titles', 'categories', 'entries.episodes'])->get()
        ->keyBy(fn (Show $show): string => $show->titles->firstWhere('is_primary', true)->title);

    expect($shows)->toHaveCount($catalog->count())->not->toBeEmpty();

    foreach ($catalog as $title => $data) {
        $show = $shows[$title];

        expect($show->titles->pluck('title')->all())->toBe($data['titles'])
            ->and($show->titles->where('is_primary', true))->toHaveCount(1)
            // A misspelled category in the catalog would be missing here
            ->and($show->categories->pluck('value')->sort()->values()->all())->toBe(collect($data['categories'])->sort()->values()->all())
            ->and($show->entries->pluck('name')->all())->toBe(array_column($data['entries'], 'name'))
            ->and($show->entries->map(fn ($entry): array => $entry->episodes->sortBy('sequence_number')->pluck('name')->all())->all())
            ->toBe(array_map(fn (array $entry): array => array_column($entry['episodes'], 'name'), $data['entries']));
    }
});

it('numbers a season file and names a film file after the film', function (): void {
    seed(DatabaseSeeder::class);

    expect(Episode::query()->where('filename', 'Breaking Bad - S02E01.mkv')->exists())->toBeTrue()
        ->and(Episode::query()->where('filename', 'Spirited Away - Spirited Away.mkv')->exists())->toBeTrue();
});

it('links catalog shows both ways, only to shows of the catalog', function (): void {
    $catalog = app(ShowCatalog::class)->shows();
    $linkTargets = $catalog->flatMap(fn (array $show): array => array_column($show['links'], 'title'));

    expect($linkTargets->diff($catalog->keys()))->toBeEmpty();

    seed(DatabaseSeeder::class);

    $id = fn (string $title): int => ShowTitle::query()->where('is_primary', true)->where('title', $title)->value('show_id');

    expect(ShowLink::query()->where('source_show_id', $id('Breaking Bad'))->where('target_show_id', $id('Better Call Saul'))->value('type'))->toBe(ShowLinkType::SpinOff)
        ->and(ShowLink::query()->where('source_show_id', $id('Better Call Saul'))->where('target_show_id', $id('Breaking Bad'))->value('type'))->toBe(ShowLinkType::Prequel);
});

it('changes nothing when it runs again', function (): void {
    $counts = fn (): array => [Show::count(), ShowTitle::count(), Episode::count(), ShowLink::count(), Category::count()];

    seed(DatabaseSeeder::class);
    $before = $counts();
    seed(DatabaseSeeder::class);

    expect($counts())->toBe($before);
});

it('leaves existing shows alone, also one with the title of a catalog show', function (): void {
    $other = Show::factory()->create();
    ShowTitle::factory()->primary()->for($other)->create(['title' => 'My own show']);
    $same = Show::factory()->create(['description' => 'Mine']);
    ShowTitle::factory()->primary()->for($same)->create(['title' => 'Better Call Saul']);

    seed(DatabaseSeeder::class);

    foreach ([$other, $same] as $show) {
        expect($show->titles()->count())->toBe(1)
            ->and($show->entries()->count())->toBe(0)
            ->and($show->categories()->count())->toBe(0)
            ->and($show->outgoingLinks()->count() + $show->incomingLinks()->count())->toBe(0);
    }

    expect($same->fresh()->description)->toBe('Mine')
        ->and(ShowTitle::query()->where('title', 'Better Call Saul')->count())->toBe(1);
});
