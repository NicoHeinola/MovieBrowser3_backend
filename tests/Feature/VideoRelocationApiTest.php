<?php

use App\Actions\Episode\VideoFile\CreateVideoFileCheckAction;
use App\Exceptions\VideoRelocationRunningException;
use App\Jobs\RelocateVideoFilesJob;
use App\Models\Episode\Episode;
use App\Models\Setting\Setting;
use App\Models\Show\Show;
use App\Models\ShowEntry\ShowEntry;
use App\Models\User\User;
use App\Models\VideoDurationScan\VideoDurationScan;
use App\Models\VideoFileCheck\VideoFileCheck;
use App\Models\VideoRelocation\VideoRelocation;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\getJson;
use function Pest\Laravel\json;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withToken;

beforeEach(function () {
    $this->oldBasePath = dirname(__DIR__, 2).'/storage/app/testing-relocation-old';
    $this->newBasePath = dirname(__DIR__, 2).'/storage/app/testing-relocation-new';
    File::deleteDirectory($this->oldBasePath);
    File::deleteDirectory($this->newBasePath);

    Setting::query()->whereKey('video_base_path')->update(['value' => $this->oldBasePath]);
});

afterEach(function () {
    File::delete("{$this->oldBasePath}-link");
    File::deleteDirectory($this->oldBasePath);
    File::deleteDirectory($this->newBasePath);
});

/**
 * An episode with a video file under the old base folder, and that file's path under both folders.
 *
 * @return array{Episode, string, string}
 */
function relocatableEpisode(ShowEntry $entry, string $name, string $contents): array
{
    $episode = Episode::factory()->for($entry, 'entry')->create(['name' => $name, 'filename' => 'video.mkv']);
    $relative = "{$entry->show_id}_{$entry->show_id}/{$entry->id}_{$entry->name}/{$episode->id}_{$name}.mkv";

    putVideoFile(test()->oldBasePath."/{$relative}", $contents);

    return [$episode, test()->oldBasePath."/{$relative}", test()->newBasePath."/{$relative}"];
}

/**
 * Runs the faked relocation jobs in order, the way the worker would, including the ones they queue. A job that throws
 * goes to failed(), as with `--tries=1`. $between runs before each job, with how many ran so far.
 */
function runRelocationJobs(?Closure $between = null): void
{
    $ran = 0;

    while (($job = Queue::pushed(RelocateVideoFilesJob::class)->values()->get($ran)) !== null) {
        $between?->__invoke($ran);
        $ran++;

        try {
            app()->call([$job, 'handle']);
        } catch (Throwable $exception) {
            $job->failed($exception);
        }
    }
}

test('relocation endpoints require an admin', function () {
    getJson('/api/v1/video-files/relocation')->assertUnauthorized();

    withToken(User::factory()->create()->createToken('test-token')->plainTextToken);

    getJson('/api/v1/video-files/relocation')->assertForbidden();
    postJson('/api/v1/video-files/relocation', ['video_base_path' => $this->newBasePath])->assertForbidden();
});

test('the status is empty before any relocation', function () {
    actingAsAdmin();

    getJson('/api/v1/video-files/relocation')
        ->assertOk()
        ->assertExactJson([
            'running' => false,
            'reverting' => false,
            'moved' => 0,
            'total' => 0,
            'from_path' => null,
            'to_path' => null,
            'finished_at' => null,
            'error' => null,
        ]);
});

test('a relocation moves every video file and then switches the setting', function () {
    actingAsAdmin();
    $entry = ShowEntry::factory()->for(Show::factory())->create(['name' => 'Season 1']);
    [, $oldOne, $newOne] = relocatableEpisode($entry, 'Episode 1', 'one');
    [, $oldTwo, $newTwo] = relocatableEpisode($entry, 'Episode 2', 'two');
    // Without a file there is nothing to move, and it doesn't count
    Episode::factory()->for($entry, 'entry')->create(['filename' => '']);

    // The sync queue runs every job before the response is built
    postJson('/api/v1/video-files/relocation', ['video_base_path' => "{$this->newBasePath}/"])
        ->assertAccepted()
        ->assertJsonPath('running', false)
        ->assertJsonPath('moved', 2)
        ->assertJsonPath('total', 2)
        ->assertJsonPath('from_path', $this->oldBasePath)
        ->assertJsonPath('to_path', $this->newBasePath)
        ->assertJsonPath('error', null);

    expect(Setting::query()->find('video_base_path')->value)->toBe($this->newBasePath)
        ->and(File::get($newOne))->toBe('one')
        ->and(File::get($newTwo))->toBe('two')
        ->and(File::exists($oldOne))->toBeFalse()
        ->and(File::exists($oldTwo))->toBeFalse()
        // Emptied show folders go, the base folder itself stays
        ->and(File::isDirectory(dirname($oldOne, 2)))->toBeFalse()
        ->and(File::isDirectory($this->oldBasePath))->toBeTrue();

    expect(getJson('/api/v1/video-files/relocation')->json('finished_at'))->not->toBeNull();
});

test('a relocation from the unset default folder moves files out of storage', function () {
    actingAsAdmin();
    $storagePath = dirname(__DIR__, 2).'/storage/app/testing-relocation-storage';
    File::deleteDirectory($storagePath);
    app()->useStoragePath($storagePath);
    Setting::query()->whereKey('video_base_path')->update(['value' => '']);

    $entry = ShowEntry::factory()->for(Show::factory())->create(['name' => 'Season 1']);
    $episode = Episode::factory()->for($entry, 'entry')->create(['name' => 'Episode 1', 'filename' => 'video.mkv']);
    $relative = "{$entry->show_id}_{$entry->show_id}/{$entry->id}_Season 1/{$episode->id}_Episode 1.mkv";
    putVideoFile("{$storagePath}/app/videos/{$relative}");

    postJson('/api/v1/video-files/relocation', ['video_base_path' => $this->newBasePath])->assertAccepted();

    expect(File::exists("{$this->newBasePath}/{$relative}"))->toBeTrue()
        ->and(File::exists("{$storagePath}/app/videos/{$relative}"))->toBeFalse();

    File::deleteDirectory($storagePath);
});

test('with nothing to move the setting is saved at once', function () {
    actingAsAdmin();
    Queue::fake();
    Episode::factory()->create(['filename' => '']);

    postJson('/api/v1/video-files/relocation', ['video_base_path' => $this->newBasePath])
        ->assertAccepted()
        ->assertJsonPath('running', false)
        ->assertJsonPath('total', 0)
        ->assertJsonPath('error', null);

    expect(Setting::query()->find('video_base_path')->value)->toBe($this->newBasePath);
    Queue::assertNothingPushed();
});

test('the new folder gets the same checks as in the setup wizard', function (string $path, string $code) {
    actingAsAdmin();
    Queue::fake();

    postJson('/api/v1/video-files/relocation', ['video_base_path' => $path])
        ->assertUnprocessable()
        ->assertJsonPath('code', $code)
        ->assertJsonPath('errors.video_base_path.0.code', $code);

    expect(VideoRelocation::query()->count())->toBe(0);
})->with([
    'relative' => ['videos', 'path_not_absolute'],
    'windows' => ['C:\\Videos', 'path_not_absolute'],
    'parent segment' => ['/media/../etc', 'path_has_parent_segment'],
    'missing' => ['', 'required'],
]);

test('the new folder must be inside a mounted media folder', function () {
    actingAsAdmin();
    config(['deploy.media_folders' => ['/mnt/d', '/mnt/e']]);

    postJson('/api/v1/video-files/relocation', ['video_base_path' => '/home/videos'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'path_outside_media_folders')
        ->assertJsonPath('params.folders', '/mnt/d, /mnt/e');
});

test('a relocation waits for a running episode length scan', function () {
    actingAsAdmin();
    VideoDurationScan::factory()->create();

    postJson('/api/v1/video-files/relocation', ['video_base_path' => $this->newBasePath])
        ->assertConflict()
        ->assertExactJson(['code' => 'video_duration_scan_running', 'params' => []]);
});

test('a relocation waits for a running video file check', function () {
    actingAsAdmin();
    VideoFileCheck::factory()->create();

    postJson('/api/v1/video-files/relocation', ['video_base_path' => $this->newBasePath])
        ->assertConflict()
        ->assertExactJson(['code' => 'video_file_check_running', 'params' => []]);

    expect(VideoRelocation::query()->exists())->toBeFalse();
});

test('a second relocation is turned away while one runs', function () {
    Queue::fake();
    actingAsAdmin();
    $running = VideoRelocation::factory()->create();

    postJson('/api/v1/video-files/relocation', ['video_base_path' => $this->newBasePath])
        ->assertConflict()
        ->assertExactJson(['code' => 'video_relocation_running', 'params' => []]);

    expect(VideoRelocation::query()->sole()->is($running))->toBeTrue();
    Queue::assertNothingPushed();
});

test('another spelling of the current folder moves and deletes nothing', function (Closure $spelling) {
    Queue::fake();
    actingAsAdmin();
    $show = Show::factory()->create();
    [, $oldOne] = relocatableEpisode(ShowEntry::factory()->for($show)->create(), 'One', 'first');

    postJson('/api/v1/video-files/relocation', ['video_base_path' => $spelling($this->oldBasePath)])->assertAccepted();

    expect(File::get($oldOne))->toBe('first');
    getJson('/api/v1/video-files/relocation')->assertJsonPath('running', false)->assertJsonPath('error', null);
    Queue::assertNothingPushed();
})->with([
    'double slash' => [fn (string $path) => dirname($path).'//'.basename($path)],
    'trailing dot' => [fn (string $path) => "{$path}/."],
    'symlink' => [function (string $path) {
        // Removed after each test
        symlink($path, "{$path}-link");

        return "{$path}-link";
    }],
]);

test('requests that touch video files are turned away while a relocation runs', function (string $method, Closure $uri) {
    actingAsAdmin();
    VideoRelocation::factory()->create();
    $episode = Episode::factory()->create();

    json($method, $uri($episode))
        ->assertStatus(409)
        ->assertExactJson(['code' => 'video_relocation_running', 'params' => []]);
})->with([
    'video file check' => ['POST', fn () => '/api/v1/video-files/check'],
    'playback' => ['POST', fn (Episode $episode) => "/api/v1/episodes/{$episode->id}/playback"],
    'create episode' => ['POST', fn (Episode $episode) => "/api/v1/show-entries/{$episode->show_entry_id}/episodes"],
    'update episode' => ['PATCH', fn (Episode $episode) => "/api/v1/episodes/{$episode->id}"],
    'delete episode' => ['DELETE', fn (Episode $episode) => "/api/v1/episodes/{$episode->id}"],
    'update entry' => ['PATCH', fn (Episode $episode) => "/api/v1/entries/{$episode->show_entry_id}"],
    'delete entry' => ['DELETE', fn (Episode $episode) => "/api/v1/entries/{$episode->show_entry_id}"],
    'delete show' => ['DELETE', fn (Episode $episode) => "/api/v1/shows/{$episode->entry->show_id}"],
    'delete shows' => ['DELETE', fn () => '/api/v1/shows'],
    'create title' => ['POST', fn (Episode $episode) => "/api/v1/shows/{$episode->entry->show_id}/titles"],
]);

test('reads and writes that touch no video file still work during a relocation', function () {
    actingAsAdmin();
    VideoRelocation::factory()->create();
    $show = Show::factory()->create();

    getJson("/api/v1/shows/{$show->id}")->assertOk();
    getJson('/api/v1/video-files/relocation')->assertOk()->assertJsonPath('running', true);
    json('PATCH', "/api/v1/shows/{$show->id}", ['description' => 'New'])->assertOk();
});

test('a failed relocation moves the moved files back and keeps the setting', function () {
    actingAsAdmin();
    Queue::fake();
    $show = Show::factory()->create();
    $first = ShowEntry::factory()->for($show)->create(['name' => 'Season 1']);
    $second = ShowEntry::factory()->for($show)->create(['name' => 'Season 2']);
    [, $oldOne, $newOne] = relocatableEpisode($first, 'Episode 1', 'one');
    [, $oldTwo, $newTwo] = relocatableEpisode($second, 'Episode 1', 'two');
    // A regular file where the second episode's folder must go fails that move after the first one succeeded
    putVideoFile(dirname($newTwo), 'blocker');

    postJson('/api/v1/video-files/relocation', ['video_base_path' => $this->newBasePath])
        ->assertAccepted()
        ->assertJsonPath('running', true);

    runRelocationJobs();

    getJson('/api/v1/video-files/relocation')
        ->assertJsonPath('running', false)
        ->assertJsonPath('reverting', true)
        ->assertJsonPath('error.code', 'video_relocation_failed')
        // The file in the old folder that couldn't move, so the admin knows what to look at
        ->assertJsonPath('error.params.path', $oldTwo);

    expect(Setting::query()->find('video_base_path')->value)->toBe($this->oldBasePath)
        ->and(File::get($oldOne))->toBe('one')
        ->and(File::get($oldTwo))->toBe('two')
        ->and(File::exists($newOne))->toBeFalse();
});

test('a failed move back ends the relocation as revert failed', function () {
    actingAsAdmin();
    Queue::fake();
    $show = Show::factory()->create();
    $first = ShowEntry::factory()->for($show)->create(['name' => 'Season 1']);
    $second = ShowEntry::factory()->for($show)->create(['name' => 'Season 2']);
    [, $oldOne, $newOne] = relocatableEpisode($first, 'Episode 1', 'one');
    [, , $newTwo] = relocatableEpisode($second, 'Episode 1', 'two');
    putVideoFile(dirname($newTwo), 'blocker');

    postJson('/api/v1/video-files/relocation', ['video_base_path' => $this->newBasePath])->assertAccepted();

    // After the first file moved, something takes its old place, so moving it back fails too
    runRelocationJobs(function (int $ran) use ($oldOne): void {
        if ($ran === 1) {
            File::ensureDirectoryExists($oldOne);
        }
    });

    getJson('/api/v1/video-files/relocation')
        ->assertJsonPath('running', false)
        ->assertJsonPath('error.code', 'video_relocation_revert_failed')
        ->assertJsonPath('error.params.path', $newOne);

    expect(Setting::query()->find('video_base_path')->value)->toBe($this->oldBasePath)
        ->and(File::get($newOne))->toBe('one');

    // It no longer blocks anything
    postJson('/api/v1/video-files/check')->assertAccepted();
});

test('the scheduled video file check skips while a relocation runs', function () {
    Queue::fake();
    VideoRelocation::factory()->create();

    expect(fn () => CreateVideoFileCheckAction::run())->toThrow(VideoRelocationRunningException::class)
        ->and(VideoFileCheck::query()->count())->toBe(0);
});

test('a failure reported after the relocation finished changes nothing', function () {
    actingAsAdmin();
    $entry = ShowEntry::factory()->for(Show::factory())->create(['name' => 'Season 1']);
    [, $oldPath, $newPath] = relocatableEpisode($entry, 'Episode 1', 'one');

    postJson('/api/v1/video-files/relocation', ['video_base_path' => $this->newBasePath])->assertAccepted();

    // A worker killed after the last job committed: the job comes back and fails on the attempt limit
    Queue::fake();
    (new RelocateVideoFilesJob(VideoRelocation::query()->sole()))->failed(new RuntimeException('Max attempts'));

    expect(Queue::pushed(RelocateVideoFilesJob::class))->toHaveCount(0)
        ->and(File::get($newPath))->toBe('one')
        ->and(File::exists($oldPath))->toBeFalse()
        ->and(Setting::query()->find('video_base_path')->value)->toBe($this->newBasePath);

    getJson('/api/v1/video-files/relocation')
        ->assertJsonPath('reverting', false)
        ->assertJsonPath('error', null);
});
