<?php

use App\Actions\VideoFileCheck\StartVideoFileCheckAction;
use App\Exceptions\VideoBaseFolderMoveRunningException;
use App\Jobs\MoveVideoBaseFolderJob;
use App\Models\Episode\Episode;
use App\Models\Setting\Setting;
use App\Models\Show\Show;
use App\Models\ShowEntry\ShowEntry;
use App\Models\User\User;
use App\Models\VideoBaseFolderMove\VideoBaseFolderMove;
use App\Models\VideoFileCheck\VideoFileCheck;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\getJson;
use function Pest\Laravel\json;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withToken;

beforeEach(function () {
    $this->oldVideoBaseFolder = dirname(__DIR__, 2).'/storage/app/testing-folder-move-old';
    $this->newVideoBaseFolder = dirname(__DIR__, 2).'/storage/app/testing-folder-move-new';
    File::deleteDirectory($this->oldVideoBaseFolder);
    File::deleteDirectory($this->newVideoBaseFolder);

    Setting::query()->whereKey('video_base_folder')->update(['value' => $this->oldVideoBaseFolder]);
});

afterEach(function () {
    File::delete("{$this->oldVideoBaseFolder}-link");
    File::deleteDirectory($this->oldVideoBaseFolder);
    File::deleteDirectory($this->newVideoBaseFolder);
});

/**
 * An episode with a video file under the old video base folder, and that file's path under both folders.
 *
 * @return array{Episode, string, string}
 */
function movableEpisode(ShowEntry $entry, string $name, string $contents): array
{
    $episode = Episode::factory()->for($entry, 'entry')->create(['name' => $name, 'filename' => 'video.mkv']);
    $relative = "{$entry->show_id}_{$entry->show_id}/{$entry->id}_{$entry->name}/{$episode->id}_{$name}.mkv";

    putVideoFile(test()->oldVideoBaseFolder."/{$relative}", $contents);

    return [$episode, test()->oldVideoBaseFolder."/{$relative}", test()->newVideoBaseFolder."/{$relative}"];
}

/**
 * Runs the faked folder move jobs in order, the way the worker would, including the ones they queue. A job that throws
 * goes to failed(), as with `--tries=1`. $between runs before each job, with how many ran so far.
 */
function runFolderMoveJobs(?Closure $between = null): void
{
    $ran = 0;

    while (($job = Queue::pushed(MoveVideoBaseFolderJob::class)->values()->get($ran)) !== null) {
        $between?->__invoke($ran);
        $ran++;

        try {
            app()->call([$job, 'handle']);
        } catch (Throwable $exception) {
            $job->failed($exception);
        }
    }
}

test('folder move endpoints require an admin', function () {
    getJson('/api/v1/video-files/move')->assertUnauthorized();

    withToken(User::factory()->create()->createToken('test-token')->plainTextToken);

    getJson('/api/v1/video-files/move')->assertForbidden();
    postJson('/api/v1/video-files/move', ['video_base_folder' => $this->newVideoBaseFolder])->assertForbidden();
});

test('the status is empty before any folder move', function () {
    actingAsAdmin();

    getJson('/api/v1/video-files/move')
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

test('a folder move moves every video file and then switches the setting', function () {
    actingAsAdmin();
    $entry = ShowEntry::factory()->for(Show::factory())->create(['name' => 'Season 1']);
    [, $oldOne, $newOne] = movableEpisode($entry, 'Episode 1', 'one');
    [, $oldTwo, $newTwo] = movableEpisode($entry, 'Episode 2', 'two');
    // Without a file there is nothing to move, and it doesn't count
    Episode::factory()->for($entry, 'entry')->create(['filename' => '']);

    // The sync queue runs every job before the response is built
    postJson('/api/v1/video-files/move', ['video_base_folder' => "{$this->newVideoBaseFolder}/"])
        ->assertAccepted()
        ->assertJsonPath('running', false)
        ->assertJsonPath('moved', 2)
        ->assertJsonPath('total', 2)
        ->assertJsonPath('from_path', $this->oldVideoBaseFolder)
        ->assertJsonPath('to_path', $this->newVideoBaseFolder)
        ->assertJsonPath('error', null);

    expect(Setting::query()->find('video_base_folder')->value)->toBe($this->newVideoBaseFolder)
        ->and(File::get($newOne))->toBe('one')
        ->and(File::get($newTwo))->toBe('two')
        ->and(File::exists($oldOne))->toBeFalse()
        ->and(File::exists($oldTwo))->toBeFalse()
        // Emptied show folders go, the video base folder itself stays
        ->and(File::isDirectory(dirname($oldOne, 2)))->toBeFalse()
        ->and(File::isDirectory($this->oldVideoBaseFolder))->toBeTrue();

    expect(getJson('/api/v1/video-files/move')->json('finished_at'))->not->toBeNull();
});

test('a folder move from the unset default folder moves files out of storage', function () {
    actingAsAdmin();
    $storagePath = dirname(__DIR__, 2).'/storage/app/testing-folder-move-storage';
    File::deleteDirectory($storagePath);
    app()->useStoragePath($storagePath);
    Setting::query()->whereKey('video_base_folder')->update(['value' => '']);

    $entry = ShowEntry::factory()->for(Show::factory())->create(['name' => 'Season 1']);
    $episode = Episode::factory()->for($entry, 'entry')->create(['name' => 'Episode 1', 'filename' => 'video.mkv']);
    $relative = "{$entry->show_id}_{$entry->show_id}/{$entry->id}_Season 1/{$episode->id}_Episode 1.mkv";
    putVideoFile("{$storagePath}/app/videos/{$relative}");

    postJson('/api/v1/video-files/move', ['video_base_folder' => $this->newVideoBaseFolder])->assertAccepted();

    expect(File::exists("{$this->newVideoBaseFolder}/{$relative}"))->toBeTrue()
        ->and(File::exists("{$storagePath}/app/videos/{$relative}"))->toBeFalse();

    File::deleteDirectory($storagePath);
});

test('with nothing to move the setting is saved by the first job', function () {
    actingAsAdmin();
    Queue::fake();
    Episode::factory()->create(['filename' => '']);

    postJson('/api/v1/video-files/move', ['video_base_folder' => $this->newVideoBaseFolder])
        ->assertAccepted()
        ->assertJsonPath('total', 0);
    runFolderMoveJobs();

    getJson('/api/v1/video-files/move')->assertJsonPath('running', false)->assertJsonPath('error', null);
    expect(Setting::query()->find('video_base_folder')->value)->toBe($this->newVideoBaseFolder);
    Queue::assertPushedTimes(MoveVideoBaseFolderJob::class, 1);
});

test('the new folder gets the same checks as in the setup wizard', function (string $path, string $code) {
    actingAsAdmin();
    Queue::fake();

    postJson('/api/v1/video-files/move', ['video_base_folder' => $path])
        ->assertUnprocessable()
        ->assertJsonPath('code', $code)
        ->assertJsonPath('errors.video_base_folder.0.code', $code);

    expect(VideoBaseFolderMove::query()->count())->toBe(0);
})->with([
    'relative' => ['videos', 'path_not_absolute'],
    'windows' => ['C:\\Videos', 'path_not_absolute'],
    'parent segment' => ['/media/../etc', 'path_has_parent_segment'],
    'missing' => ['', 'required'],
]);

test('the new folder must be inside a mounted media folder', function () {
    actingAsAdmin();
    config(['deploy.media_folders' => ['/mnt/d', '/mnt/e']]);

    postJson('/api/v1/video-files/move', ['video_base_folder' => '/home/videos'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'path_outside_media_folders')
        ->assertJsonPath('params.folders', '/mnt/d, /mnt/e');
});

test('a folder move waits for a running video file check', function () {
    actingAsAdmin();
    VideoFileCheck::factory()->create();

    postJson('/api/v1/video-files/move', ['video_base_folder' => $this->newVideoBaseFolder])
        ->assertConflict()
        ->assertExactJson(['code' => 'video_file_check_running', 'params' => []]);

    expect(VideoBaseFolderMove::query()->exists())->toBeFalse();
});

test('a second folder move is turned away while one runs', function () {
    Queue::fake();
    actingAsAdmin();
    $running = VideoBaseFolderMove::factory()->create();

    postJson('/api/v1/video-files/move', ['video_base_folder' => $this->newVideoBaseFolder])
        ->assertConflict()
        ->assertExactJson(['code' => 'video_base_folder_move_running', 'params' => []]);

    expect(VideoBaseFolderMove::query()->sole()->is($running))->toBeTrue();
    Queue::assertNothingPushed();
});

test('another spelling of the current folder moves and deletes nothing', function (Closure $spelling) {
    Queue::fake();
    actingAsAdmin();
    $show = Show::factory()->create();
    [, $oldOne] = movableEpisode(ShowEntry::factory()->for($show)->create(), 'One', 'first');

    postJson('/api/v1/video-files/move', ['video_base_folder' => $spelling($this->oldVideoBaseFolder)])->assertAccepted();
    runFolderMoveJobs();

    expect(File::get($oldOne))->toBe('first');
    getJson('/api/v1/video-files/move')->assertJsonPath('running', false)->assertJsonPath('error', null);
    Queue::assertPushedTimes(MoveVideoBaseFolderJob::class, 1);
})->with([
    'double slash' => [fn (string $path) => dirname($path).'//'.basename($path)],
    'trailing dot' => [fn (string $path) => "{$path}/."],
    'symlink' => [function (string $path) {
        // Removed after each test
        symlink($path, "{$path}-link");

        return "{$path}-link";
    }],
]);

test('requests that touch video files are turned away while a folder move runs', function (string $method, Closure $uri) {
    actingAsAdmin();
    VideoBaseFolderMove::factory()->create();
    $episode = Episode::factory()->create();

    json($method, $uri($episode))
        ->assertStatus(409)
        ->assertExactJson(['code' => 'video_base_folder_move_running', 'params' => []]);
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

test('reads and writes that touch no video file still work during a folder move', function () {
    actingAsAdmin();
    VideoBaseFolderMove::factory()->create();
    $show = Show::factory()->create();

    getJson("/api/v1/shows/{$show->id}")->assertOk();
    getJson('/api/v1/video-files/move')->assertOk()->assertJsonPath('running', true);
    json('PATCH', "/api/v1/shows/{$show->id}", ['description' => 'New'])->assertOk();
});

test('a failed folder move moves the moved files back and keeps the setting', function () {
    actingAsAdmin();
    Queue::fake();
    $show = Show::factory()->create();
    $first = ShowEntry::factory()->for($show)->create(['name' => 'Season 1']);
    $second = ShowEntry::factory()->for($show)->create(['name' => 'Season 2']);
    [, $oldOne, $newOne] = movableEpisode($first, 'Episode 1', 'one');
    [, $oldTwo, $newTwo] = movableEpisode($second, 'Episode 1', 'two');
    // A regular file where the second episode's folder must go fails that move after the first one succeeded
    putVideoFile(dirname($newTwo), 'blocker');

    postJson('/api/v1/video-files/move', ['video_base_folder' => $this->newVideoBaseFolder])
        ->assertAccepted()
        ->assertJsonPath('running', true);

    runFolderMoveJobs();

    getJson('/api/v1/video-files/move')
        ->assertJsonPath('running', false)
        ->assertJsonPath('reverting', true)
        ->assertJsonPath('error.code', 'video_base_folder_move_failed')
        // The file in the old folder that couldn't move, so the admin knows what to look at
        ->assertJsonPath('error.params.path', $oldTwo);

    expect(Setting::query()->find('video_base_folder')->value)->toBe($this->oldVideoBaseFolder)
        ->and(File::get($oldOne))->toBe('one')
        ->and(File::get($oldTwo))->toBe('two')
        ->and(File::exists($newOne))->toBeFalse();
});

test('a failed move back ends the folder move as revert failed', function () {
    actingAsAdmin();
    Queue::fake();
    $show = Show::factory()->create();
    $first = ShowEntry::factory()->for($show)->create(['name' => 'Season 1']);
    $second = ShowEntry::factory()->for($show)->create(['name' => 'Season 2']);
    [, $oldOne, $newOne] = movableEpisode($first, 'Episode 1', 'one');
    [, , $newTwo] = movableEpisode($second, 'Episode 1', 'two');
    putVideoFile(dirname($newTwo), 'blocker');

    postJson('/api/v1/video-files/move', ['video_base_folder' => $this->newVideoBaseFolder])->assertAccepted();

    // After the first file moved, something takes its old place, so moving it back fails too
    runFolderMoveJobs(function (int $ran) use ($oldOne): void {
        if ($ran === 1) {
            File::ensureDirectoryExists($oldOne);
        }
    });

    getJson('/api/v1/video-files/move')
        ->assertJsonPath('running', false)
        ->assertJsonPath('error.code', 'video_base_folder_move_revert_failed')
        ->assertJsonPath('error.params.path', $newOne);

    expect(Setting::query()->find('video_base_folder')->value)->toBe($this->oldVideoBaseFolder)
        ->and(File::get($newOne))->toBe('one');

    // It no longer blocks anything
    postJson('/api/v1/video-files/check')->assertAccepted();
});

test('the scheduled video file check skips while a folder move runs', function () {
    Queue::fake();
    VideoBaseFolderMove::factory()->create();

    expect(fn () => StartVideoFileCheckAction::run())->toThrow(VideoBaseFolderMoveRunningException::class)
        ->and(VideoFileCheck::query()->count())->toBe(0);
});

test('a failure reported after the folder move finished changes nothing', function () {
    actingAsAdmin();
    $entry = ShowEntry::factory()->for(Show::factory())->create(['name' => 'Season 1']);
    [, $oldPath, $newPath] = movableEpisode($entry, 'Episode 1', 'one');

    postJson('/api/v1/video-files/move', ['video_base_folder' => $this->newVideoBaseFolder])->assertAccepted();

    // A worker killed after the last job committed: the job comes back and fails on the attempt limit
    Queue::fake();
    (new MoveVideoBaseFolderJob(VideoBaseFolderMove::query()->sole()))->failed(new RuntimeException('Max attempts'));

    expect(Queue::pushed(MoveVideoBaseFolderJob::class))->toHaveCount(0)
        ->and(File::get($newPath))->toBe('one')
        ->and(File::exists($oldPath))->toBeFalse()
        ->and(Setting::query()->find('video_base_folder')->value)->toBe($this->newVideoBaseFolder);

    getJson('/api/v1/video-files/move')
        ->assertJsonPath('reverting', false)
        ->assertJsonPath('error', null);
});
