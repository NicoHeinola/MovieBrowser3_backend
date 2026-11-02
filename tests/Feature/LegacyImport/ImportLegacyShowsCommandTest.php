<?php

use App\Actions\Episode\VideoFile\CopyVideoFileAction;
use App\Actions\Episode\VideoFile\ResolveEpisodeVideoPathAction;
use App\Actions\Episode\VideoFile\ResolveFreeDiskSpaceAction;
use App\Actions\LegacyImport\CreateLegacyShowImportAction;
use App\Dtos\LegacyImport\LegacyShowData;
use App\Enums\ShowEntryType;
use App\Enums\WatchStatus;
use App\Models\Category\Category;
use App\Models\Episode\Episode;
use App\Models\LegacyShowImport\LegacyShowImport;
use App\Models\Setting\Setting;
use App\Models\Show\Show;
use App\Models\ShowEntry\ShowEntry;
use App\Models\ShowEntryWatchStatus\ShowEntryWatchStatus;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\PendingCommand;

use function Pest\Laravel\artisan;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

function legacySourceRoot(): string
{
    return dirname(__DIR__, 3).'/storage/app/testing-legacy-source';
}

function legacyExportPath(): string
{
    return legacySourceRoot().'-export.json';
}

/**
 * An episode as the MovieBrowser2 export has it, with its video written under the test source root unless `$withFile` is false.
 *
 * @return array<string, mixed>
 */
function legacyEpisode(int $id, int $number, string $folder, string $type = 'episode', string $title = '', bool $withFile = true): array
{
    $filename = "episode_{$number}_{$id}.mkv";
    $relative = "{$folder}/{$filename}";

    if ($withFile) {
        putVideoFile(legacySourceRoot().'/'.$relative, "original-{$id}");
    }

    return [
        'id' => $id,
        'title' => $title,
        'description' => '',
        'number' => $number,
        'type' => $type,
        'filename' => $filename,
        'file_path' => ['relative' => $relative, 'absolute' => "/mnt/g/New_Vid/{$relative}"],
        'file_size_bytes' => null,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function legacyShow(int $id, array $overrides = []): array
{
    $folder = "show_{$id}/season_1";

    return [
        'id' => $id,
        'title' => "Show {$id}",
        'description' => '',
        'image' => "https://example.com/{$id}.png",
        'folder_name' => "show_{$id}",
        'folder_path' => ['relative' => "show_{$id}", 'absolute' => "/mnt/g/New_Vid/show_{$id}"],
        'categories' => [],
        'seasons' => [[
            'id' => $id * 10,
            'title' => '',
            'description' => '',
            'image' => '',
            'number' => 1,
            'folder_name' => 'season_1',
            'folder_path' => ['relative' => $folder, 'absolute' => "/mnt/g/New_Vid/{$folder}"],
            'episodes' => [legacyEpisode($id * 100 + 1, 1, $folder), legacyEpisode($id * 100 + 2, 2, $folder)],
        ]],
        'user_show_statuses' => [],
        'user_watch_seasons' => [],
        ...$overrides,
    ];
}

/**
 * @param  list<array<string, mixed>>  $shows
 */
function writeLegacyExport(array $shows): string
{
    File::put(legacyExportPath(), json_encode($shows));

    return legacyExportPath();
}

/**
 * Every file under the source root with its contents, to prove the import left the originals alone.
 *
 * @return array<string, string>
 */
function legacySourceSnapshot(): array
{
    return collect(File::allFiles(legacySourceRoot()))
        ->mapWithKeys(fn (SplFileInfo $file): array => [$file->getPathname() => File::get($file->getPathname())])
        ->sortKeys()
        ->all();
}

function importLegacyShows(string $file, array $options = []): PendingCommand
{
    return artisan('moviebrowser2:import-shows', ['file' => $file, '--source-root' => legacySourceRoot(), ...$options]);
}

beforeEach(function () {
    File::ensureDirectoryExists(legacySourceRoot());
    Setting::query()->whereKey('setup_completed_at')->update(['value' => now()->toIso8601String()]);
});

afterEach(function () {
    File::deleteDirectory(legacySourceRoot());
    File::delete(legacyExportPath());
});

test('it imports a show and copies its videos without touching the originals', function () {
    $file = writeLegacyExport([legacyShow(1, ['description' => 'A show'])]);
    $before = legacySourceSnapshot();

    importLegacyShows($file)
        ->expectsConfirmation('Import 1 shows and copy their videos into '.videoBasePathForTests().'?', 'yes')
        ->assertSuccessful();

    $show = Show::query()->sole();
    expect($show->banner_url)->toBe('https://example.com/1.png')
        ->and($show->card_image_url)->toBe('https://example.com/1.png')
        ->and($show->description)->toBe('A show')
        ->and($show->titles()->sole()->only('title', 'is_primary'))->toBe(['title' => 'Show 1', 'is_primary' => true]);

    $entry = $show->entries()->sole();
    expect($entry->type)->toBe(ShowEntryType::Season)
        ->and($entry->name)->toBe('Season 1');

    $episodes = Episode::query()->orderBy('sequence_number')->get();
    expect($episodes->pluck('name')->all())->toBe(['Episode 1', 'Episode 2'])
        ->and($episodes->pluck('filename')->all())->toBe(['episode_1_101.mkv', 'episode_2_102.mkv']);

    $copy = ResolveEpisodeVideoPathAction::run($episodes[0]);
    expect($copy)->toBe(videoBasePathForTests()."/{$show->id}_Show 1/{$entry->id}_Season 1/{$episodes[0]->id}_Episode 1.mkv")
        ->and(File::get($copy))->toBe('original-101')
        ->and(File::get(ResolveEpisodeVideoPathAction::run($episodes[1])))->toBe('original-102')
        ->and(File::glob(videoBasePathForTests().'/*/*/*.part'))->toBe([])
        ->and(legacySourceSnapshot())->toBe($before);

    assertDatabaseHas('legacy_show_imports', ['legacy_show_id' => 1, 'show_id' => $show->id]);
});

test('a dry run writes nothing', function () {
    $file = writeLegacyExport([legacyShow(1)]);

    importLegacyShows($file, ['--dry-run' => true])
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();

    assertDatabaseCount('shows', 0);
    expect(File::isDirectory(videoBasePathForTests()) ? File::allFiles(videoBasePathForTests()) : [])->toBe([]);
});

test('declining the confirmation imports nothing', function () {
    $file = writeLegacyExport([legacyShow(1)]);

    importLegacyShows($file)
        ->expectsConfirmation('Import 1 shows and copy their videos into '.videoBasePathForTests().'?', 'no')
        ->assertSuccessful();

    assertDatabaseCount('shows', 0);
});

test('a second run skips shows that were already imported', function () {
    $file = writeLegacyExport([legacyShow(1), legacyShow(2)]);
    importLegacyShows($file, ['--only' => [1], '--force' => true])->assertSuccessful();

    importLegacyShows($file, ['--force' => true])
        ->expectsOutputToContain('[1/1] Show 2')
        ->assertSuccessful();

    assertDatabaseCount('shows', 2);
    assertDatabaseCount('legacy_show_imports', 2);
});

test('it reads the absolute paths from the export without a source root', function () {
    $show = legacyShow(1);
    $show['seasons'][0]['episodes'][0]['file_path']['absolute'] = legacySourceRoot().'/'.$show['seasons'][0]['episodes'][0]['file_path']['relative'];
    $show['seasons'][0]['episodes'][1]['file_path']['absolute'] = legacySourceRoot().'/'.$show['seasons'][0]['episodes'][1]['file_path']['relative'];
    $file = writeLegacyExport([$show]);

    artisan('moviebrowser2:import-shows', ['file' => $file, '--force' => true])->assertSuccessful();

    expect(Episode::query()->where('filename', '!=', '')->count())->toBe(2);
});

test('an episode whose video is missing is imported without one', function () {
    $show = legacyShow(1);
    $show['seasons'][0]['episodes'][] = legacyEpisode(103, 3, 'show_1/season_1', withFile: false);
    $file = writeLegacyExport([$show]);

    importLegacyShows($file, ['--force' => true])
        ->expectsOutputToContain('video missing')
        ->assertSuccessful();

    expect(Episode::query()->where('sequence_number', 3)->sole()->filename)->toBe('');
});

test('it refuses to start when none of the videos can be found', function () {
    $file = writeLegacyExport([legacyShow(1)]);

    importLegacyShows($file, ['--source-root' => legacySourceRoot().'/elsewhere', '--force' => true])
        ->expectsOutputToContain('None of the videos were found')
        ->assertFailed();

    assertDatabaseCount('shows', 0);
});

test('it refuses to start without enough free space for the copies', function () {
    ResolveFreeDiskSpaceAction::mock()->shouldReceive('handle')->andReturn(10);
    $file = writeLegacyExport([legacyShow(1)]);

    importLegacyShows($file, ['--force' => true])
        ->expectsOutputToContain('Not enough free space')
        ->assertFailed();

    assertDatabaseCount('shows', 0);
});

test('it refuses to run before the first-login setup picked a video folder', function () {
    Setting::query()->whereKey('setup_completed_at')->update(['value' => '']);
    $file = writeLegacyExport([legacyShow(1)]);

    importLegacyShows($file, ['--force' => true])
        ->expectsOutputToContain('Finish the first-login setup')
        ->assertFailed();

    assertDatabaseCount('shows', 0);
});

test('it fails cleanly on a file that is not there', function () {
    artisan('moviebrowser2:import-shows', ['file' => legacySourceRoot().'/nope.json'])->assertFailed();
});

test('seasons of only movies or OVAs become movie and TV special entries, mixed ones stay seasons in watch order', function () {
    $show = legacyShow(1);
    $show['seasons'][0]['episodes'][] = legacyEpisode(103, 3, 'show_1/season_1', 'ova', 'Beach OVA');
    $show['seasons'][] = [
        'id' => 20, 'title' => '', 'number' => 3,
        'episodes' => [legacyEpisode(301, 1, 'show_1/movie', 'movie', '  The   Movie ')],
    ];
    $show['seasons'][] = [
        'id' => 30, 'title' => 'Specials', 'number' => 2,
        'episodes' => [legacyEpisode(201, 1, 'show_1/ova', 'ova'), legacyEpisode(202, 2, 'show_1/ova', 'ova')],
    ];
    $file = writeLegacyExport([$show]);

    importLegacyShows($file, ['--force' => true])->assertSuccessful();

    $entries = ShowEntry::query()->orderBy('sort_order')->get();
    expect($entries->map(fn (ShowEntry $entry): array => [$entry->type, $entry->name, $entry->sort_order])->all())->toBe([
        [ShowEntryType::Season, 'Season 1', 1],
        [ShowEntryType::TvSpecial, 'Specials', 2],
        [ShowEntryType::Movie, 'The Movie', 3],
    ])
        ->and($entries[0]->episodes()->orderBy('sequence_number')->pluck('name')->all())->toBe(['Episode 1', 'Episode 2', 'Beach OVA'])
        ->and($entries[1]->episodes()->orderBy('sequence_number')->pluck('name')->all())->toBe(['OVA 1', 'OVA 2']);
});

test('categories match existing ones ignoring case and punctuation, and missing ones are created', function () {
    $existing = Category::factory()->create(['name' => 'Slice of Life', 'value' => 'slice-of-life']);
    $file = writeLegacyExport([legacyShow(1, ['categories' => [['id' => 1, 'name' => 'slice_of_life'], ['id' => 2, 'name' => 'sci-fi']]])]);

    importLegacyShows($file, ['--force' => true])->assertSuccessful();

    $created = Category::query()->where('value', 'sci-fi')->sole();
    expect($created->name)->toBe('Sci-fi')
        ->and(Show::query()->sole()->categories->pluck('id')->sort()->values()->all())->toBe([$existing->id, $created->id]);
});

test('watch statuses go to the matching user, splitting by the season they were on', function () {
    // The migrations seed the admin as "Admin"; MovieBrowser2 calls it "admin"
    $user = User::query()->usernameIgnoringCase('admin')->sole();
    $completed = legacyShow(1, ['user_show_statuses' => [['id' => 1, 'user_id' => 1, 'username' => 'admin', 'status' => 'completed']]]);
    $completed['seasons'][] = ['id' => 11, 'title' => '', 'number' => 2, 'episodes' => []];

    $dropped = legacyShow(2, [
        'user_show_statuses' => [
            ['id' => 2, 'user_id' => 1, 'username' => 'admin', 'status' => 'dropped'],
            ['id' => 3, 'user_id' => 2, 'username' => 'ghost', 'status' => 'watching'],
        ],
        'user_watch_seasons' => [['id' => 1, 'user_id' => 1, 'username' => 'admin', 'season_id' => 21]],
    ]);
    $dropped['seasons'][] = ['id' => 21, 'title' => '', 'number' => 2, 'episodes' => []];
    $dropped['seasons'][] = ['id' => 22, 'title' => '', 'number' => 3, 'episodes' => []];
    $file = writeLegacyExport([$completed, $dropped]);

    importLegacyShows($file, ['--force' => true])
        ->expectsOutputToContain('no MovieBrowser3 user "ghost"')
        ->assertSuccessful();

    $statusesFor = fn (int $legacyShowId): array => ShowEntry::query()
        ->where('show_id', LegacyShowImport::query()->where('legacy_show_id', $legacyShowId)->value('show_id'))
        ->orderBy('sort_order')
        ->get()
        ->map(fn (ShowEntry $entry): ?WatchStatus => ShowEntryWatchStatus::query()->where('show_entry_id', $entry->id)->where('user_id', $user->id)->value('status'))
        ->all();

    expect($statusesFor(1))->toBe([WatchStatus::Completed, WatchStatus::Completed])
        ->and($statusesFor(2))->toBe([WatchStatus::Completed, WatchStatus::Dropped, null])
        ->and(ShowEntryWatchStatus::query()->count())->toBe(4);
});

test('a failure partway through a show rolls back its rows and copies but keeps the originals', function () {
    $show = legacyShow(1);
    $before = legacySourceSnapshot();
    $copied = 0;

    expect(function () use ($show, &$copied): void {
        CreateLegacyShowImportAction::run(LegacyShowData::from($show), legacySourceRoot(), onFileCopied: function () use (&$copied): void {
            $copied++;

            throw new RuntimeException('Cancelled.');
        });
    })->toThrow(RuntimeException::class, 'Cancelled.');

    expect($copied)->toBe(1);
    assertDatabaseCount('shows', 0);
    assertDatabaseCount('episodes', 0);
    assertDatabaseCount('legacy_show_imports', 0);
    expect(File::isDirectory(videoBasePathForTests()) ? File::allFiles(videoBasePathForTests()) : [])->toBe([])
        ->and(legacySourceSnapshot())->toBe($before);
});

test('a show whose copy fails is reported and the rest still import', function () {
    $file = writeLegacyExport([legacyShow(1), legacyShow(2)]);
    $before = legacySourceSnapshot();
    // The first show's first copy lands here (the next show and entry ids are 1); a file already there must never be overwritten
    $blocker = putVideoFile(videoBasePathForTests().'/1_Show 1/1_Season 1/1_Episode 1.mkv', 'already-here');

    importLegacyShows($file, ['--force' => true])
        ->expectsOutputToContain('Refusing to overwrite')
        ->assertFailed();

    expect(File::get($blocker))->toBe('already-here')
        ->and(LegacyShowImport::query()->pluck('legacy_show_id')->all())->toBe([2])
        ->and(legacySourceSnapshot())->toBe($before);
});

test('a video that vanishes after the check fails the show instead of importing the episode without it', function () {
    $show = legacyShow(1);
    // The check saw both videos; the drive drops out before the import reads the second
    File::delete(legacySourceRoot().'/'.$show['seasons'][0]['episodes'][1]['file_path']['relative']);

    expect(fn () => CreateLegacyShowImportAction::run(LegacyShowData::from($show), legacySourceRoot()))
        ->toThrow(RuntimeException::class, 'does not exist');

    assertDatabaseCount('shows', 0);
    expect(File::isDirectory(videoBasePathForTests()) ? File::allFiles(videoBasePathForTests()) : [])->toBe([]);
});

test('shows imported with fewer videos than the originals are reported, and redone with --redo-incomplete', function () {
    $file = writeLegacyExport([legacyShow(1), legacyShow(2)]);
    importLegacyShows($file, ['--force' => true])->assertSuccessful();
    $before = legacySourceSnapshot();

    // What the drive dropping out left behind: a show whose episodes came in without their videos
    $broken = LegacyShowImport::query()->where('legacy_show_id', 1)->sole()->show_id;
    $brokenEpisodes = Episode::query()->whereHas('entry', fn ($query) => $query->where('show_id', $broken))->get();
    $brokenEpisodes->each(fn (Episode $episode) => File::delete(ResolveEpisodeVideoPathAction::run($episode)));
    Episode::query()->whereKey($brokenEpisodes->modelKeys())->update(['filename' => '']);

    importLegacyShows($file, ['--force' => true])
        ->expectsOutputToContain('Show 1 was imported with 0 of 2 videos.')
        ->expectsOutputToContain('--redo-incomplete')
        ->expectsOutputToContain('Nothing to import.')
        ->assertSuccessful();

    importLegacyShows($file, ['--redo-incomplete' => true])
        ->expectsConfirmation('Delete 1 incompletely imported shows, then import 1 shows and copy their videos into '.videoBasePathForTests().'?', 'yes')
        ->assertSuccessful();

    $redone = LegacyShowImport::query()->where('legacy_show_id', 1)->sole()->show_id;
    $episodes = Episode::query()->whereHas('entry', fn ($query) => $query->where('show_id', $redone))->get();

    expect($redone)->not->toBe($broken)
        ->and(Show::query()->whereKey($broken)->exists())->toBeFalse()
        ->and($episodes->map(fn (Episode $episode): string => File::get(ResolveEpisodeVideoPathAction::run($episode)))->sort()->values()->all())->toBe(['original-101', 'original-102'])
        ->and(LegacyShowImport::query()->count())->toBe(2)
        ->and(legacySourceSnapshot())->toBe($before);
});

test('it stops after three shows in a row fail instead of failing every show', function () {
    $file = writeLegacyExport([legacyShow(1), legacyShow(2), legacyShow(3), legacyShow(4)]);
    CreateLegacyShowImportAction::mock()->shouldReceive('handle')->andThrow(new RuntimeException('The video to copy does not exist'));

    importLegacyShows($file, ['--force' => true])
        ->expectsOutputToContain('[3/4] Show 3')
        ->doesntExpectOutputToContain('[4/4] Show 4')
        ->expectsOutputToContain('3 shows in a row failed')
        ->expectsOutputToContain('Stopped. 1 shows were not started')
        ->assertFailed();
});

test('a copy reads and writes the whole video in chunks', function () {
    $source = putVideoFile(legacySourceRoot().'/big.mkv', str_repeat('0123456789abcdef', 1024 * 1024));
    $target = videoBasePathForTests().'/1_Show/1_Season/1_Episode.mkv';

    $bytes = DB::transaction(fn (): int => CopyVideoFileAction::run($source, $target));

    expect($bytes)->toBe(16 * 1024 * 1024)
        ->and(md5_file($target))->toBe(md5_file($source));
});
