<?php

use App\Actions\Episode\VideoFile\Path\ResolveEpisodeVideoPathAction;
use App\Actions\VideoFileCheck\CheckEpisodeVideoFilesAction;
use App\Actions\VideoFileCheck\StartVideoFileCheckAction;
use App\Enums\VideoFileCheckErrorCode;
use App\Jobs\CheckEpisodeVideoFilesJob;
use App\Models\Episode\Episode;
use App\Models\ShowEntry\ShowEntry;
use App\Models\User\User;
use App\Models\VideoFileCheck\VideoFileCheck;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\getJson;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travel;
use function Pest\Laravel\withToken;

/**
 * Gives the video base folder content, as a mounted drive would: the check only needs the folder, playback needs it
 * non-empty.
 */
function mountVideoBaseFolder(): void
{
    putVideoFile(videoBaseFolderForTests().'/other-show/video.mkv');
}

function episodeWithVideoFile(array $attributes = [], string $contents = 'video-bytes'): Episode
{
    $episode = Episode::factory()->create($attributes);
    putVideoFile(ResolveEpisodeVideoPathAction::run($episode), $contents);

    return $episode;
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

test('an admin queues the check and gets its status', function () {
    Queue::fake();
    actingAsAdmin();
    mountVideoBaseFolder();

    postJson('/api/v1/video-files/check')->assertAccepted()->assertJsonPath('running', true);

    Queue::assertPushed(CheckEpisodeVideoFilesJob::class);
});

test('a check started without a reachable video base folder ends with a code', function () {
    actingAsAdmin();
    $episode = Episode::factory()->create();

    postJson('/api/v1/video-files/check')->assertAccepted();

    getJson('/api/v1/video-files/check')
        ->assertJsonPath('running', false)
        ->assertJsonPath('error.code', 'video_base_folder_unavailable');

    expect(getJson('/api/v1/video-files/check')->getContent())->toContain('"params":{}')
        ->and($episode->fresh()->has_video_file)->toBeTrue();
});

test('an unreadable video base folder stops the check like a missing one', function () {
    if (posix_getuid() === 0) {
        $this->markTestSkipped('Root can read every folder.');
    }

    $episode = episodeWithVideoFile();
    chmod(videoBaseFolderForTests(), 0);

    try {
        StartVideoFileCheckAction::run();
    } finally {
        // The folder must be readable again for the cleanup after each test
        chmod(videoBaseFolderForTests(), 0755);
    }

    expect($episode->fresh()->has_video_file)->toBeTrue()
        ->and(VideoFileCheck::query()->sole()->error_code)->toBe(VideoFileCheckErrorCode::VideoBaseFolderUnavailable);
});

test('an empty video base folder does not stop the admin from starting a check', function () {
    Queue::fake();
    actingAsAdmin();
    Episode::factory()->create();
    File::ensureDirectoryExists(videoBaseFolderForTests());

    postJson('/api/v1/video-files/check')->assertAccepted();

    Queue::assertPushedTimes(CheckEpisodeVideoFilesJob::class, 1);
});

test('the check stores each found video file size', function () {
    $present = Episode::factory()->create();
    putVideoFile(ResolveEpisodeVideoPathAction::run($present), str_repeat('x', 42));

    StartVideoFileCheckAction::run();

    expect($present->fresh()->video_file_size)->toBe(42);
});

test('the check stores which episodes have their video file', function () {
    $present = episodeWithVideoFile();
    $returned = episodeWithVideoFile();
    $returned->update(['has_video_file' => false]);
    $gone = Episode::factory()->create();
    $withoutFilename = Episode::factory()->create(['filename' => '']);

    StartVideoFileCheckAction::run();

    expect($present->fresh()->has_video_file)->toBeTrue()
        ->and($returned->fresh()->has_video_file)->toBeTrue()
        ->and($gone->fresh()->has_video_file)->toBeFalse()
        ->and($withoutFilename->fresh()->has_video_file)->toBeFalse();
});

test('the check changes nothing when the video base folder does not exist', function () {
    $episode = Episode::factory()->create();

    StartVideoFileCheckAction::run();

    expect($episode->fresh()->has_video_file)->toBeTrue()
        ->and(VideoFileCheck::query()->sole()->error_code)->toBe(VideoFileCheckErrorCode::VideoBaseFolderUnavailable);
});

test('the check flags episodes as missing without an error when the video base folder is empty', function () {
    $episode = Episode::factory()->create();
    File::ensureDirectoryExists(videoBaseFolderForTests());

    StartVideoFileCheckAction::run();

    expect($episode->fresh()->has_video_file)->toBeFalse()
        ->and(VideoFileCheck::query()->sole()->error_code)->toBeNull();
});

test('a deleted show folder only flags that show as missing', function () {
    $kept = episodeWithVideoFile();
    $deleted = episodeWithVideoFile();
    File::deleteDirectory(dirname(ResolveEpisodeVideoPathAction::run($deleted), 2));

    StartVideoFileCheckAction::run();

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

    StartVideoFileCheckAction::run();

    getJson('/api/v1/video-files/check')
        ->assertJsonPath('error', null)
        ->assertJsonPath('missing_video_files_count', 1);
});

test('the next check restores the flags once the drive is back', function () {
    $episode = episodeWithVideoFile();
    $path = ResolveEpisodeVideoPathAction::run($episode);
    File::deleteDirectory(videoBaseFolderForTests());
    File::ensureDirectoryExists(videoBaseFolderForTests());

    StartVideoFileCheckAction::run();
    expect($episode->fresh()->has_video_file)->toBeFalse();

    putVideoFile($path);
    StartVideoFileCheckAction::run();

    expect($episode->fresh()->has_video_file)->toBeTrue();
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
            'unknown_video_durations_count' => 0,
        ]);
});

test('pressing the button again during a check starts nothing new', function () {
    Queue::fake();
    actingAsAdmin();
    mountVideoBaseFolder();

    postJson('/api/v1/video-files/check')->assertAccepted();
    postJson('/api/v1/video-files/check')->assertAccepted();

    Queue::assertPushedTimes(CheckEpisodeVideoFilesJob::class, 1);
});

test('the daily run starts nothing new during a check either', function () {
    Queue::fake();

    StartVideoFileCheckAction::run();
    StartVideoFileCheckAction::run();

    Queue::assertPushedTimes(CheckEpisodeVideoFilesJob::class, 1);
});

test('the status shows progress while the check runs', function () {
    actingAsAdmin();
    VideoFileCheck::factory()->create(['checked' => 40, 'total' => 90]);

    getJson('/api/v1/video-files/check')
        ->assertJsonPath('running', true)
        ->assertJsonPath('checked', 40)
        ->assertJsonPath('total', 90);
});

test('a finished check stores how many episodes it checked', function () {
    mountVideoBaseFolder();
    Episode::factory()->count(3)->create();

    StartVideoFileCheckAction::run();

    expect(VideoFileCheck::query()->sole()->only('checked', 'total'))->toBe(['checked' => 3, 'total' => 3]);
});

test('a finished check reports its time and the button works again', function () {
    $this->freezeTime();
    actingAsAdmin();
    mountVideoBaseFolder();

    postJson('/api/v1/video-files/check')->assertAccepted();

    getJson('/api/v1/video-files/check')
        ->assertJsonPath('running', false)
        ->assertJsonPath('finished_at', now()->startOfSecond()->toJSON())
        ->assertJsonPath('error', null);

    Queue::fake();
    postJson('/api/v1/video-files/check')->assertAccepted();

    Queue::assertPushedTimes(CheckEpisodeVideoFilesJob::class, 1);
});

test('a library that expects no files checks fine without a video base folder', function () {
    actingAsAdmin();
    $episode = Episode::factory()->create(['filename' => '']);

    postJson('/api/v1/video-files/check')->assertAccepted();

    getJson('/api/v1/video-files/check')
        ->assertJsonPath('running', false)
        ->assertJsonPath('error', null);

    expect($episode->fresh()->has_video_file)->toBeFalse();
});

test('the database allows only one running check', function () {
    VideoFileCheck::factory()->create();

    expect(fn () => VideoFileCheck::factory()->create())->toThrow(UniqueConstraintViolationException::class);
});

test('a finished check replaces the previous result', function () {
    mountVideoBaseFolder();

    StartVideoFileCheckAction::run();
    StartVideoFileCheckAction::run();

    expect(VideoFileCheck::query()->sole()->finished_at)->not->toBeNull();
});

test('a job of a check deleted as stale ends without doing anything', function () {
    mountVideoBaseFolder();
    $episode = Episode::factory()->create();
    $check = VideoFileCheck::factory()->create();
    $check->delete();

    CheckEpisodeVideoFilesJob::dispatch($check);

    expect($episode->fresh()->has_video_file)->toBeTrue();
});

test('a check that fails reports the code and frees the button at once', function () {
    actingAsAdmin();
    CheckEpisodeVideoFilesAction::mock()->shouldReceive('handle')->once()->andThrow(new RuntimeException('Disk error.'));

    expect(fn () => StartVideoFileCheckAction::run())->toThrow(RuntimeException::class);

    getJson('/api/v1/video-files/check')
        ->assertJsonPath('running', false)
        ->assertJsonPath('error.code', 'video_file_check_failed');
});

test('the next successful check clears a reported error', function () {
    actingAsAdmin();
    Episode::factory()->create();
    StartVideoFileCheckAction::run();

    mountVideoBaseFolder();
    StartVideoFileCheckAction::run();

    getJson('/api/v1/video-files/check')->assertJsonPath('error', null);
});

test('a check whose worker died stops counting as running', function () {
    Queue::fake();
    actingAsAdmin();
    mountVideoBaseFolder();
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
    mountVideoBaseFolder();
    $episode = Episode::factory()->create(['has_video_file' => false]);

    post("/api/v1/episodes/{$episode->id}", [
        '_method' => 'PATCH',
        'file' => UploadedFile::fake()->create('replacement.mkv', 1),
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('has_video_file', true);
});

test('an upload is refused while the video drive is not there, and writes nothing', function () {
    actingAsAdmin();
    $episode = Episode::factory()->create(['filename' => 'old.mkv', 'has_video_file' => false]);

    post("/api/v1/episodes/{$episode->id}", [
        '_method' => 'PATCH',
        'name' => 'Renamed',
        'file' => UploadedFile::fake()->create('replacement.mkv', 1),
    ], ['Accept' => 'application/json'])
        ->assertConflict()
        ->assertJsonPath('code', 'video_base_folder_unavailable');

    expect($episode->fresh()->only('name', 'filename'))->toBe(['name' => $episode->name, 'filename' => 'old.mkv'])
        ->and(File::exists(videoBaseFolderForTests()))->toBeFalse();
});

test('the check reads unknown lengths and those of replaced files, and keeps the rest', function () {
    $unknown = episodeWithVideoFile(contents: matroskaVideo(1440.4));
    $replaced = episodeWithVideoFile(['video_duration_seconds' => 1500, 'video_file_size' => 1], matroskaVideo(1620));
    $known = episodeWithVideoFile(['video_duration_seconds' => 1200, 'video_file_size' => null], matroskaVideo(1440));
    $unreadable = episodeWithVideoFile();
    $gone = Episode::factory()->create(['video_duration_seconds' => 1200, 'video_file_size' => 99]);

    StartVideoFileCheckAction::run();

    expect($unknown->fresh()->video_duration_seconds)->toBe(1440)
        ->and($replaced->fresh()->video_duration_seconds)->toBe(1620)
        ->and($known->fresh()->video_duration_seconds)->toBe(1200)
        ->and($unreadable->fresh()->video_duration_seconds)->toBeNull()
        // Kept, for when its drive is back
        ->and($gone->fresh()->only('has_video_file', 'video_file_size', 'video_duration_seconds'))
        ->toBe(['has_video_file' => false, 'video_file_size' => 99, 'video_duration_seconds' => 1200]);

    actingAsAdmin();
    getJson('/api/v1/video-files/check')
        ->assertJsonPath('missing_video_files_count', 1)
        ->assertJsonPath('unknown_video_durations_count', 1);
});

test('an uploaded video gets its length read and episodes expose it', function () {
    actingAsAdmin();
    $entry = ShowEntry::factory()->create();

    $response = postJson("/api/v1/show-entries/{$entry->id}/episodes", [
        'name' => 'Episode 1',
        'sequence_number' => 1,
        'file' => UploadedFile::fake()->createWithContent('episode-1.mkv', matroskaVideo(1440)),
    ])->assertCreated();

    getJson("/api/v1/episodes/{$response->json('id')}")->assertJsonPath('video_duration_seconds', 1440);
});
