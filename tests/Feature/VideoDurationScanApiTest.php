<?php

use App\Actions\Episode\QueueEpisodeVideoDurationsAction;
use App\Actions\Episode\VideoFile\CheckVideoBasePathExistsAction;
use App\Actions\Episode\VideoFile\CreateVideoFileCheckAction;
use App\Actions\Episode\VideoFile\ReadEpisodeVideoDurationsAction;
use App\Actions\Episode\VideoFile\ReadVideoFileDurationAction;
use App\Actions\Episode\VideoFile\ResolveEpisodeVideoPathAction;
use App\Actions\VideoDurationScan\CreateVideoDurationScanAction;
use App\Actions\VideoDurationScan\UpdateVideoDurationScanProgressAction;
use App\Enums\VideoDurationScanErrorCode;
use App\Jobs\ReadEpisodeVideoDurationsJob;
use App\Models\Episode\Episode;
use App\Models\Show\Show;
use App\Models\ShowEntry\ShowEntry;
use App\Models\User\User;
use App\Models\VideoDurationScan\VideoDurationScan;
use App\Models\VideoRelocation\VideoRelocation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travel;
use function Pest\Laravel\withToken;

function episodeWithVideoLength(?float $seconds, array $attributes = []): Episode
{
    $episode = Episode::factory()->create($attributes);
    putVideoFile(ResolveEpisodeVideoPathAction::run($episode), matroskaVideo($seconds));

    return $episode;
}

function runVideoDurationScan(): bool
{
    $scan = VideoDurationScan::query()->active()->first() ?? VideoDurationScan::factory()->create();

    return app(ReadEpisodeVideoDurationsAction::class)->handle($scan);
}

test('the length scan requires authentication', function () {
    postJson('/api/v1/video-files/duration-scan')->assertUnauthorized();
    getJson('/api/v1/video-files/duration-scan')->assertUnauthorized();
});

test('the length scan requires an admin', function () {
    withToken(User::factory()->create()->createToken('test-token')->plainTextToken);

    postJson('/api/v1/video-files/duration-scan')->assertForbidden();
    getJson('/api/v1/video-files/duration-scan')->assertForbidden();
});

test('an admin starts a scan of every episode with a file and gets its status', function () {
    Queue::fake();
    actingAsAdmin();
    episodeWithVideoLength(1440);
    episodeWithVideoLength(1500, ['video_duration_seconds' => 1]);
    Episode::factory()->create(['filename' => '']);
    Episode::factory()->create(['has_video_file' => false]);

    postJson('/api/v1/video-files/duration-scan')
        ->assertAccepted()
        ->assertExactJson([
            'running' => true,
            'read' => 0,
            'total' => 2,
            'finished_at' => null,
            'error' => null,
            'unknown_durations_count' => 0,
        ]);

    Queue::assertPushedTimes(ReadEpisodeVideoDurationsJob::class, 1);
});

test('the scan stores each episode length and ends', function () {
    actingAsAdmin();
    $first = episodeWithVideoLength(1440.4);
    $second = episodeWithVideoLength(1500, ['video_duration_seconds' => 1]);

    postJson('/api/v1/video-files/duration-scan')->assertAccepted();

    expect($first->fresh()->video_duration_seconds)->toBe(1440)
        ->and($second->fresh()->video_duration_seconds)->toBe(1500)
        ->and(Episode::query()->whereNotNull('video_duration_queued_at')->exists())->toBeFalse();

    getJson('/api/v1/video-files/duration-scan')
        ->assertJsonPath('running', false)
        ->assertJsonPath('error', null)
        ->assertJsonPath('unknown_durations_count', 0)
        ->assertJsonPath('finished_at', fn (?string $finishedAt): bool => $finishedAt !== null);
});

test('a scan of some shows only reads their episodes', function () {
    actingAsAdmin();
    $show = Show::factory()->create();
    $inShow = episodeWithVideoLength(1440, ['show_entry_id' => ShowEntry::factory()->for($show)]);
    $elsewhere = episodeWithVideoLength(1440);

    postJson('/api/v1/video-files/duration-scan', ['show_ids' => [$show->id]])->assertAccepted();

    expect($inShow->fresh()->video_duration_seconds)->toBe(1440)
        ->and($elsewhere->fresh()->video_duration_seconds)->toBeNull();
});

test('the scan validates the shows', function () {
    actingAsAdmin();

    postJson('/api/v1/video-files/duration-scan', ['show_ids' => []])->assertJsonValidationErrors(['show_ids']);
    postJson('/api/v1/video-files/duration-scan', ['show_ids' => [999]])->assertJsonValidationErrors(['show_ids.0']);
    postJson('/api/v1/video-files/duration-scan', ['only_missing' => 'some'])->assertJsonValidationErrors(['only_missing']);
});

test('reading missing lengths skips episodes that have one', function () {
    actingAsAdmin();
    $known = episodeWithVideoLength(1500, ['video_duration_seconds' => 1]);
    $unknown = episodeWithVideoLength(1440);
    // Such as one a scan left waiting when it lost the drive
    $waiting = episodeWithVideoLength(1620, ['video_duration_queued_at' => now()->subDay()]);

    postJson('/api/v1/video-files/duration-scan', ['only_missing' => true])->assertAccepted();

    expect($known->fresh()->video_duration_seconds)->toBe(1)
        ->and($unknown->fresh()->video_duration_seconds)->toBe(1440)
        ->and($waiting->fresh()->video_duration_seconds)->toBe(1620);
});

test('the unknown count holds every episode with a file but no length', function () {
    actingAsAdmin();
    episodeWithVideoLength(1440);
    episodeWithVideoLength(1440, ['video_duration_queued_at' => now()->subDay()]);
    episodeWithVideoLength(1440, ['video_duration_seconds' => 1440]);
    Episode::factory()->create(['has_video_file' => false]);

    getJson('/api/v1/video-files/duration-scan')->assertJsonPath('unknown_durations_count', 2);
});

test('a scan with nothing to read starts nothing', function () {
    Queue::fake();
    actingAsAdmin();
    Episode::factory()->create(['has_video_file' => false]);

    postJson('/api/v1/video-files/duration-scan')->assertAccepted()->assertJsonPath('running', false);

    Queue::assertNothingPushed();
    expect(VideoDurationScan::query()->exists())->toBeFalse();
});

test('episodes queued during a scan join it instead of starting another', function () {
    Queue::fake();
    $first = episodeWithVideoLength(1440);
    $second = episodeWithVideoLength(1440);

    QueueEpisodeVideoDurationsAction::run(episodeIds: [$first->id]);
    QueueEpisodeVideoDurationsAction::run(episodeIds: [$second->id]);

    Queue::assertPushedTimes(ReadEpisodeVideoDurationsJob::class, 1);
    expect(VideoDurationScan::query()->sole()->total)->toBe(2);
});

test('a file that can not be read gets no length and counts as unknown', function () {
    actingAsAdmin();
    $episode = Episode::factory()->create();
    putVideoFile(ResolveEpisodeVideoPathAction::run($episode), 'not a video');

    postJson('/api/v1/video-files/duration-scan')->assertAccepted();

    expect($episode->fresh()->video_duration_seconds)->toBeNull()
        ->and($episode->fresh()->video_duration_queued_at)->toBeNull();

    getJson('/api/v1/video-files/duration-scan')->assertJsonPath('unknown_durations_count', 1);
});

test('an unreachable base folder ends the scan and keeps its episodes queued for the next one', function () {
    actingAsAdmin();
    $episode = Episode::factory()->create();

    postJson('/api/v1/video-files/duration-scan')->assertAccepted();

    getJson('/api/v1/video-files/duration-scan')
        ->assertJsonPath('running', false)
        ->assertJsonPath('error.code', 'video_base_path_unavailable');
    expect($episode->fresh()->video_duration_queued_at)->not->toBeNull();

    putVideoFile(ResolveEpisodeVideoPathAction::run($episode), matroskaVideo(1440));
    CreateVideoDurationScanAction::run();

    expect($episode->fresh()->video_duration_seconds)->toBe(1440);
});

test('the scan writes nothing when the base folder is lost during a chunk', function () {
    $episode = episodeWithVideoLength(1440, ['video_duration_queued_at' => now()]);
    CheckVideoBasePathExistsAction::mock()->shouldReceive('handle')->twice()->andReturn(true, false);

    expect(runVideoDurationScan())->toBeFalse()
        ->and($episode->fresh()->video_duration_seconds)->toBeNull()
        ->and($episode->fresh()->video_duration_queued_at)->not->toBeNull()
        ->and(VideoDurationScan::query()->sole()->error_code)->toBe(VideoDurationScanErrorCode::VideoBasePathUnavailable);
});

test('a chunk stops taking files when its time is up and the next job goes on', function () {
    config(['video_files.duration_scan_chunk_seconds' => 0]);
    $first = episodeWithVideoLength(1440, ['video_duration_queued_at' => now()]);
    $second = episodeWithVideoLength(1500, ['video_duration_queued_at' => now()]);
    $scan = VideoDurationScan::factory()->create(['total' => 2]);
    Queue::fake();

    (new ReadEpisodeVideoDurationsJob($scan))->handle(app(ReadEpisodeVideoDurationsAction::class));

    expect($first->fresh()->video_duration_seconds)->toBe(1440)
        ->and($second->fresh()->video_duration_seconds)->toBeNull()
        ->and($scan->fresh()->only(['read', 'total', 'running']))->toBe(['read' => 1, 'total' => 2, 'running' => true]);
    Queue::assertPushed(ReadEpisodeVideoDurationsJob::class, fn (ReadEpisodeVideoDurationsJob $job): bool => $job->scan->is($scan));
});

test('an episode queued again while its file was read keeps waiting for the new file', function () {
    $episode = episodeWithVideoLength(1440, ['video_duration_queued_at' => now()->subMinute()]);

    // The file is replaced, and queued again, between the read and the write
    ReadVideoFileDurationAction::mock()->shouldReceive('handle')->andReturnUsing(function () use ($episode): int {
        $episode->update(['video_duration_queued_at' => now()]);

        return 1440;
    });

    runVideoDurationScan();

    expect($episode->fresh()->video_duration_seconds)->toBeNull()
        ->and($episode->fresh()->video_duration_queued_at)->not->toBeNull();
});

test('an episode queued as a scan ends gets a new scan instead of waiting', function () {
    $first = episodeWithVideoLength(1440, ['video_duration_queued_at' => now()]);
    $late = episodeWithVideoLength(1500);
    $real = new UpdateVideoDurationScanProgressAction;
    $hasQueuedLate = false;

    // The late upload lands after the progress count, so the first scan still thinks it read everything
    UpdateVideoDurationScanProgressAction::mock()->shouldReceive('handle')
        ->andReturnUsing(function (VideoDurationScan $scan, int $read) use ($real, $late, &$hasQueuedLate): VideoDurationScan {
            $result = $real->handle($scan, $read);

            if (!$hasQueuedLate) {
                $hasQueuedLate = true;
                $late->update(['video_duration_queued_at' => now()]);
            }

            return $result;
        });

    runVideoDurationScan();

    expect($first->fresh()->video_duration_seconds)->toBe(1440)
        ->and($late->fresh()->video_duration_seconds)->toBe(1500)
        // The first scan's result was replaced by the second one, which read only the late episode
        ->and(VideoDurationScan::query()->sole()->total)->toBe(1);
});

test('a failed job ends the scan as failed and keeps its episodes queued', function () {
    $episode = episodeWithVideoLength(1440, ['video_duration_queued_at' => now()]);
    $scan = VideoDurationScan::factory()->create();

    (new ReadEpisodeVideoDurationsJob($scan))->failed();

    expect($scan->fresh()->error_code)->toBe(VideoDurationScanErrorCode::Failed)
        ->and($scan->fresh()->running)->toBeNull()
        ->and($episode->fresh()->video_duration_queued_at)->not->toBeNull();
});

test('a finished scan replaces the previous result', function () {
    episodeWithVideoLength(1440);
    QueueEpisodeVideoDurationsAction::run();
    $previous = VideoDurationScan::query()->sole();

    QueueEpisodeVideoDurationsAction::run();

    expect(VideoDurationScan::query()->sole()->id)->not->toBe($previous->id);
});

test('a stale scan gives way to a new one', function () {
    Queue::fake();
    $stale = VideoDurationScan::factory()->create();
    travel(10)->minutes();
    $episode = episodeWithVideoLength(1440);

    QueueEpisodeVideoDurationsAction::run(episodeIds: [$episode->id]);

    expect(VideoDurationScan::query()->whereKey($stale->id)->exists())->toBeFalse();
    Queue::assertPushedTimes(ReadEpisodeVideoDurationsJob::class, 1);
});

test('a scan can not start while video files are being moved', function () {
    actingAsAdmin();
    VideoRelocation::factory()->create();
    $episode = episodeWithVideoLength(1440);

    postJson('/api/v1/video-files/duration-scan')->assertConflict()->assertJsonPath('code', 'video_relocation_running');

    // The refused start rolls the queuing back too
    expect($episode->fresh()->video_duration_queued_at)->toBeNull();
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

test('the file check reads no lengths and forgets those of missing and replaced files', function () {
    $unknown = episodeWithVideoLength(1440);
    $firstMeasured = episodeWithVideoLength(1440, ['video_duration_seconds' => 1440, 'video_file_size' => null]);
    $replaced = episodeWithVideoLength(1620, ['video_duration_seconds' => 1500, 'video_file_size' => 1]);
    $gone = Episode::factory()->create(['video_duration_seconds' => 1200, 'video_duration_queued_at' => now()]);

    CreateVideoFileCheckAction::run();

    // No scan starts: reading lengths again is left to the admin
    expect(VideoDurationScan::query()->exists())->toBeFalse()
        ->and($unknown->fresh()->video_duration_seconds)->toBeNull()
        ->and($firstMeasured->fresh()->video_duration_seconds)->toBe(1440)
        ->and($replaced->fresh()->only(['video_duration_seconds', 'video_duration_queued_at']))->toBe(['video_duration_seconds' => null, 'video_duration_queued_at' => null])
        ->and($gone->fresh()->only(['video_duration_seconds', 'video_duration_queued_at']))->toBe(['video_duration_seconds' => null, 'video_duration_queued_at' => null]);
});
