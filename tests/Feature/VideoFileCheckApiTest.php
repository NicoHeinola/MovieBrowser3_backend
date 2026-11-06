<?php

use App\Actions\Episode\VideoFile\CheckEpisodeVideoFilesAction;
use App\Actions\Episode\VideoFile\CheckVideoBasePathExistsAction;
use App\Actions\Episode\VideoFile\CreateVideoFileCheckAction;
use App\Actions\Episode\VideoFile\ResolveEpisodeVideoPathAction;
use App\Actions\Episode\VideoFile\ResolveVideoBasePathAction;
use App\Actions\EpisodePlayback\OpenVlcPlaylistAction;
use App\Dtos\EpisodePlayback\VlcConnectionData;
use App\Enums\VideoFileCheckErrorCode;
use App\Jobs\CheckEpisodeVideoFilesJob;
use App\Models\Episode\Episode;
use App\Models\Setting\Setting;
use App\Models\ShowEntry\ShowEntry;
use App\Models\User\User;
use App\Models\VideoFileCheck\VideoFileCheck;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\getJson;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travel;
use function Pest\Laravel\withToken;

/**
 * Gives the base folder content, as a mounted drive would: the check only needs the folder, playback needs it non-empty.
 */
function mountVideoBasePath(): void
{
    putVideoFile(videoBasePathForTests().'/other-show/video.mkv');
}

function episodeWithVideoFile(array $attributes = []): Episode
{
    $episode = Episode::factory()->create($attributes);
    putVideoFile(ResolveEpisodeVideoPathAction::run($episode));

    return $episode;
}

function runVideoFileCheck(int $afterId = 0): void
{
    $check = VideoFileCheck::query()->active()->first() ?? VideoFileCheck::factory()->create();

    (new CheckEpisodeVideoFilesJob($check, $afterId))->handle(app(CheckEpisodeVideoFilesAction::class));
}

test('video file check requires authentication', function () {
    postJson('/api/v1/video-files/check')->assertUnauthorized();
    getJson('/api/v1/video-files/check')->assertUnauthorized();
});

test('video file check requires an admin', function () {
    withToken(User::factory()->create()->createToken('test-token')->plainTextToken);

    postJson('/api/v1/video-files/check')->assertForbidden();
    getJson('/api/v1/video-files/check')->assertForbidden();
});

test('an admin queues the check and gets an empty 202', function () {
    Queue::fake();
    actingAsAdmin();
    mountVideoBasePath();

    postJson('/api/v1/video-files/check')->assertAccepted()->assertContent('');

    Queue::assertPushed(CheckEpisodeVideoFilesJob::class, fn (CheckEpisodeVideoFilesJob $job): bool => $job->afterId === 0);
});

test('video file check answers 422 with a code when the base folder does not exist', function () {
    Queue::fake();
    actingAsAdmin();
    Episode::factory()->create();

    postJson('/api/v1/video-files/check')
        ->assertUnprocessable()
        ->assertExactJson([
            'code' => 'video_base_path_unavailable',
            'params' => [],
            'errors' => ['video_base_path' => [['code' => 'video_base_path_unavailable', 'params' => []]]],
        ]);

    Queue::assertNothingPushed();
});

test('an unreadable base folder stops the check like a missing one', function () {
    if (posix_getuid() === 0) {
        $this->markTestSkipped('Root can read every folder.');
    }

    Queue::fake();
    actingAsAdmin();
    $episode = episodeWithVideoFile();
    chmod(videoBasePathForTests(), 0);

    try {
        postJson('/api/v1/video-files/check')
            ->assertUnprocessable()
            ->assertJsonPath('code', 'video_base_path_unavailable');

        Queue::assertNothingPushed();

        runVideoFileCheck();
    } finally {
        // The folder must be readable again for the cleanup after each test
        chmod(videoBasePathForTests(), 0755);
    }

    expect($episode->fresh()->has_video_file)->toBeTrue()
        ->and(VideoFileCheck::query()->sole()->error_code)->toBe(VideoFileCheckErrorCode::VideoBasePathUnavailable);
});

test('an empty base folder does not stop the admin from starting a check', function () {
    Queue::fake();
    actingAsAdmin();
    Episode::factory()->create();
    File::ensureDirectoryExists(videoBasePathForTests());

    postJson('/api/v1/video-files/check')->assertAccepted();

    Queue::assertPushedTimes(CheckEpisodeVideoFilesJob::class, 1);
});

test('the check stores which episodes have their video file', function () {
    $present = episodeWithVideoFile();
    $returned = episodeWithVideoFile();
    $returned->update(['has_video_file' => false]);
    $gone = Episode::factory()->create();
    $withoutFilename = Episode::factory()->create(['filename' => '']);

    CreateVideoFileCheckAction::run();

    expect($present->fresh()->has_video_file)->toBeTrue()
        ->and($returned->fresh()->has_video_file)->toBeTrue()
        ->and($gone->fresh()->has_video_file)->toBeFalse()
        ->and($withoutFilename->fresh()->has_video_file)->toBeFalse();
});

test('the check changes nothing when the base folder does not exist', function () {
    $episode = Episode::factory()->create();

    CreateVideoFileCheckAction::run();

    expect($episode->fresh()->has_video_file)->toBeTrue()
        ->and(VideoFileCheck::query()->sole()->error_code)->toBe(VideoFileCheckErrorCode::VideoBasePathUnavailable);
});

test('the check flags episodes as missing without an error when the base folder is empty', function () {
    $episode = Episode::factory()->create();
    File::ensureDirectoryExists(videoBasePathForTests());

    CreateVideoFileCheckAction::run();

    expect($episode->fresh()->has_video_file)->toBeFalse()
        ->and(VideoFileCheck::query()->sole()->error_code)->toBeNull();
});

test('the check writes nothing when the base folder is lost during a chunk', function () {
    $episode = Episode::factory()->create();
    CheckVideoBasePathExistsAction::mock()->shouldReceive('handle')->twice()->andReturn(true, false);

    Queue::fake();
    runVideoFileCheck();

    expect($episode->fresh()->has_video_file)->toBeTrue();
    Queue::assertNothingPushed();
});

test('a deleted show folder only flags that show as missing', function () {
    $kept = episodeWithVideoFile();
    $deleted = episodeWithVideoFile();
    File::deleteDirectory(dirname(ResolveEpisodeVideoPathAction::run($deleted), 2));

    CreateVideoFileCheckAction::run();

    expect($kept->fresh()->has_video_file)->toBeTrue()
        ->and($deleted->fresh()->has_video_file)->toBeFalse()
        ->and(VideoFileCheck::query()->sole()->error_code)->toBeNull();
});

test('the status counts files that went missing, not episodes that never had one', function () {
    actingAsAdmin();
    episodeWithVideoFile();
    $deleted = episodeWithVideoFile();
    Episode::factory()->create(['filename' => '']);
    File::deleteDirectory(dirname(ResolveEpisodeVideoPathAction::run($deleted), 2));

    CreateVideoFileCheckAction::run();

    getJson('/api/v1/video-files/check')
        ->assertJsonPath('error', null)
        ->assertJsonPath('missing_video_files_count', 1);
});

test('the next check restores the flags once the drive is back', function () {
    $episode = episodeWithVideoFile();
    $path = ResolveEpisodeVideoPathAction::run($episode);
    File::deleteDirectory(videoBasePathForTests());
    File::ensureDirectoryExists(videoBasePathForTests());

    CreateVideoFileCheckAction::run();
    expect($episode->fresh()->has_video_file)->toBeFalse();

    putVideoFile($path);
    CreateVideoFileCheckAction::run();

    expect($episode->fresh()->has_video_file)->toBeTrue();
});

test('the check goes through every chunk, one job each', function () {
    mountVideoBasePath();
    $entry = ShowEntry::factory()->create();
    Episode::factory()->count(CheckEpisodeVideoFilesAction::CHUNK_SIZE + 1)->create(['show_entry_id' => $entry->id]);

    CreateVideoFileCheckAction::run();

    expect(Episode::query()->where('has_video_file', true)->count())->toBe(0);
});

test('a full chunk queues the next one and the last chunk queues nothing', function () {
    mountVideoBasePath();
    $entry = ShowEntry::factory()->create();
    $episodes = Episode::factory()->count(CheckEpisodeVideoFilesAction::CHUNK_SIZE)->create(['show_entry_id' => $entry->id]);
    Queue::fake();

    runVideoFileCheck();

    Queue::assertPushed(CheckEpisodeVideoFilesJob::class, fn (CheckEpisodeVideoFilesJob $job): bool => $job->afterId === $episodes->last()->id);

    Queue::fake();
    runVideoFileCheck($episodes->last()->id);

    Queue::assertNothingPushed();
});

test('the check runs the same number of queries however many shows it covers', function () {
    mountVideoBasePath();
    Episode::factory()->create();

    $countQueries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        runVideoFileCheck();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $queriesForOneShow = $countQueries();
    Episode::factory()->count(4)->create();

    expect($countQueries())->toBe($queriesForOneShow);
});

test('the check sees a base path changed after the worker memoized the old one', function () {
    $oldBasePath = videoBasePathForTests().'/old-base';
    $episode = Episode::factory()->create();
    Setting::query()->whereKey('video_base_path')->update(['value' => $oldBasePath]);
    ResolveVideoBasePathAction::run();

    Setting::query()->whereKey('video_base_path')->update(['value' => videoBasePathForTests()]);
    mountVideoBasePath();
    runVideoFileCheck();

    expect($episode->fresh()->has_video_file)->toBeFalse();
});

test('the check is scheduled daily', function () {
    Artisan::call('schedule:list');

    expect(Artisan::output())->toContain('0 0 * * *')->toContain('check-episode-video-files');
});

test('the status is empty before any check ran', function () {
    actingAsAdmin();

    getJson('/api/v1/video-files/check')
        ->assertOk()
        ->assertExactJson([
            'running' => false,
            'checked' => 0,
            'total' => 0,
            'finished_at' => null,
            'error' => null,
            'missing_video_files_count' => 0,
        ]);
});

test('pressing the button again during a check answers 422 with a code and starts nothing', function () {
    Queue::fake();
    actingAsAdmin();
    mountVideoBasePath();

    postJson('/api/v1/video-files/check')->assertAccepted();

    postJson('/api/v1/video-files/check')
        ->assertUnprocessable()
        ->assertExactJson([
            'code' => 'video_file_check_running',
            'params' => [],
            'errors' => ['video_file_check' => [['code' => 'video_file_check_running', 'params' => []]]],
        ]);

    Queue::assertPushedTimes(CheckEpisodeVideoFilesJob::class, 1);
});

test('the daily run starts nothing new during a check either', function () {
    Queue::fake();

    CreateVideoFileCheckAction::run();
    CreateVideoFileCheckAction::run();

    Queue::assertPushedTimes(CheckEpisodeVideoFilesJob::class, 1);
});

test('the status shows progress while the check runs', function () {
    Queue::fake();
    actingAsAdmin();
    mountVideoBasePath();
    $entry = ShowEntry::factory()->create();
    Episode::factory()->count(CheckEpisodeVideoFilesAction::CHUNK_SIZE + 5)->create(['show_entry_id' => $entry->id]);

    postJson('/api/v1/video-files/check')->assertAccepted();

    getJson('/api/v1/video-files/check')
        ->assertJsonPath('running', true)
        ->assertJsonPath('checked', 0)
        ->assertJsonPath('total', CheckEpisodeVideoFilesAction::CHUNK_SIZE + 5);

    runVideoFileCheck();

    getJson('/api/v1/video-files/check')
        ->assertJsonPath('running', true)
        ->assertJsonPath('checked', CheckEpisodeVideoFilesAction::CHUNK_SIZE)
        ->assertJsonPath('total', CheckEpisodeVideoFilesAction::CHUNK_SIZE + 5);
});

test('a finished check reports its time and the button works again', function () {
    $this->freezeTime();
    actingAsAdmin();
    mountVideoBasePath();

    postJson('/api/v1/video-files/check')->assertAccepted();

    getJson('/api/v1/video-files/check')
        ->assertJsonPath('running', false)
        ->assertJsonPath('finished_at', now()->startOfSecond()->toJSON())
        ->assertJsonPath('error', null);

    Queue::fake();
    postJson('/api/v1/video-files/check')->assertAccepted();

    Queue::assertPushedTimes(CheckEpisodeVideoFilesJob::class, 1);
});

test('a library that expects no files checks fine without a base folder', function () {
    actingAsAdmin();
    $episode = Episode::factory()->create(['filename' => '']);

    postJson('/api/v1/video-files/check')->assertAccepted();

    getJson('/api/v1/video-files/check')
        ->assertJsonPath('running', false)
        ->assertJsonPath('error', null);

    expect($episode->fresh()->has_video_file)->toBeFalse();
});

test('a check stopped by a missing base folder reports the code', function () {
    actingAsAdmin();
    Episode::factory()->create();

    CreateVideoFileCheckAction::run();

    getJson('/api/v1/video-files/check')
        ->assertJsonPath('running', false)
        ->assertJsonPath('error.code', 'video_base_path_unavailable');

    expect(getJson('/api/v1/video-files/check')->getContent())->toContain('"params":{}');
});

test('the database allows only one running check', function () {
    VideoFileCheck::factory()->create();

    expect(fn () => VideoFileCheck::factory()->create())->toThrow(UniqueConstraintViolationException::class);
});

test('each run keeps its own row', function () {
    mountVideoBasePath();

    CreateVideoFileCheckAction::run();
    CreateVideoFileCheckAction::run();

    expect(VideoFileCheck::query()->whereNotNull('finished_at')->count())->toBe(2)
        ->and(VideoFileCheck::query()->where('running', true)->count())->toBe(0);
});

test('a job of a check deleted as stale ends without doing anything', function () {
    mountVideoBasePath();
    $episode = Episode::factory()->create();
    $check = VideoFileCheck::factory()->create();
    $check->delete();

    CheckEpisodeVideoFilesJob::dispatch($check);

    expect($episode->fresh()->has_video_file)->toBeTrue();
});

test('a check that fails reports the code and frees the button at once', function () {
    actingAsAdmin();
    CheckEpisodeVideoFilesAction::mock()->shouldReceive('handle')->once()->andThrow(new RuntimeException('Disk error.'));

    expect(fn () => CreateVideoFileCheckAction::run())->toThrow(RuntimeException::class);

    getJson('/api/v1/video-files/check')
        ->assertJsonPath('running', false)
        ->assertJsonPath('error.code', 'video_file_check_failed');
});

test('a finished run stores how many episodes it checked', function (int $extraEpisodes) {
    mountVideoBasePath();
    $entry = ShowEntry::factory()->create();
    $count = CheckEpisodeVideoFilesAction::CHUNK_SIZE + $extraEpisodes;
    Episode::factory()->count($count)->create(['show_entry_id' => $entry->id]);

    CreateVideoFileCheckAction::run();

    expect(VideoFileCheck::query()->sole()->only('checked', 'total'))->toBe(['checked' => $count, 'total' => $count]);
})->with(['ending mid chunk' => 3, 'ending exactly on a chunk' => 0]);

test('the next successful check clears a reported error', function () {
    actingAsAdmin();
    Episode::factory()->create();
    CreateVideoFileCheckAction::run();

    mountVideoBasePath();
    CreateVideoFileCheckAction::run();

    getJson('/api/v1/video-files/check')->assertJsonPath('error', null);
});

test('a check whose worker died stops counting as running', function () {
    Queue::fake();
    actingAsAdmin();
    mountVideoBasePath();
    postJson('/api/v1/video-files/check')->assertAccepted();

    travel(config('video_files.check_stale_after_seconds') + 1)->seconds();

    getJson('/api/v1/video-files/check')->assertJsonPath('running', false);

    postJson('/api/v1/video-files/check')->assertAccepted();

    Queue::assertPushedTimes(CheckEpisodeVideoFilesJob::class, 2);
});

test('the episode payload reads the stored value without touching the disk', function () {
    actingAsAdmin();
    $flagged = Episode::factory()->create(['has_video_file' => false]);
    putVideoFile(ResolveEpisodeVideoPathAction::run($flagged));
    $unchecked = Episode::factory()->create();

    getJson("/api/v1/episodes/{$flagged->id}")->assertOk()->assertJsonPath('has_video_file', false);
    getJson("/api/v1/episodes/{$unchecked->id}")->assertOk()->assertJsonPath('has_video_file', true);
});

test('a new episode has no video file until one is uploaded', function () {
    actingAsAdmin();
    $entry = ShowEntry::factory()->create();

    postJson("/api/v1/show-entries/{$entry->id}/episodes", ['name' => 'No file', 'sequence_number' => 1])
        ->assertCreated()
        ->assertJsonPath('has_video_file', false);

    post("/api/v1/show-entries/{$entry->id}/episodes", [
        'name' => 'With file',
        'sequence_number' => 2,
        'file' => UploadedFile::fake()->create('episode.mkv', 1),
    ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('has_video_file', true);
});

test('uploading a replacement clears the missing flag', function () {
    actingAsAdmin();
    $episode = Episode::factory()->create(['has_video_file' => false]);

    post("/api/v1/episodes/{$episode->id}", [
        '_method' => 'PATCH',
        'file' => UploadedFile::fake()->create('replacement.mkv', 1),
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('has_video_file', true);
});

test('a failed playback check flags the episode as missing', function () {
    actingAsAdmin();
    mountVideoBasePath();
    $episode = Episode::factory()->create();

    postJson("/api/v1/episodes/{$episode->id}/playback")
        ->assertUnprocessable()
        ->assertJsonPath('code', 'no_video_file')
        ->assertJsonPath('errors.episode.0.code', 'no_video_file');

    expect($episode->fresh()->has_video_file)->toBeFalse();
});

test('a failed playback check keeps the stored value when the base folder is unreachable', function () {
    actingAsAdmin();
    $episode = Episode::factory()->create();

    postJson("/api/v1/episodes/{$episode->id}/playback")
        ->assertUnprocessable()
        ->assertJsonPath('code', 'no_video_file');

    expect($episode->fresh()->has_video_file)->toBeTrue();
});

test('a successful playback check clears the missing flag', function () {
    Queue::fake();
    actingAsAdmin();
    $episode = episodeWithVideoFile(['has_video_file' => false]);
    OpenVlcPlaylistAction::mock()->shouldReceive('handle')->once()->andReturn(new VlcConnectionData(port: 45678, password: 'secret'));

    postJson("/api/v1/episodes/{$episode->id}/playback")->assertCreated();

    expect($episode->fresh()->has_video_file)->toBeTrue();
});
