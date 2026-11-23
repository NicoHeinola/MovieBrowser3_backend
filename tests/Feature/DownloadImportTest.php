<?php

use App\Actions\Download\ImportDownloadPickAction;
use App\Actions\Download\SyncDownloadsAction;
use App\Actions\Episode\VideoFile\CheckWindowsCopyAvailableAction;
use App\Actions\Episode\VideoFile\ResolveEpisodeVideoPathAction;
use App\Actions\Episode\VideoFile\ResolveFreeDiskSpaceAction;
use App\Enums\DownloadErrorCode;
use App\Enums\DownloadStatus;
use App\Jobs\ImportDownloadPickJob;
use App\Models\Download\Download;
use App\Models\DownloadPick\DownloadPick;
use App\Models\Episode\Episode;
use App\Models\Setting\Setting;
use App\Models\VideoRelocation\VideoRelocation;
use App\Services\QBittorrent\QBittorrentConnector;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Saloon\Laravel\Facades\Saloon;

use function Pest\Laravel\postJson;

const IMPORT_HASH = '12c03cf9cc60ea7c13931e0195fa54903d8af85f';

beforeEach(function () {
    Saloon::fake([]);
    Queue::fake();
    // Plain PHP copies in tests, never robocopy
    CheckWindowsCopyAvailableAction::mock()->shouldReceive('handle')->andReturn(false);

    foreach ([
        'qbittorrent_url' => 'http://qbittorrent.test',
        'qbittorrent_username' => 'admin',
        'qbittorrent_password' => 'secret',
        'download_path' => videoBasePathForTests().'/downloads',
    ] as $key => $value) {
        Setting::query()->find($key)->update(['value' => $value]);
    }
});

/**
 * A download of one show with one picked file per given file index, each for an episode without a video.
 *
 * @param  list<int>  $fileIndexes
 * @return array{Download, list<DownloadPick>}
 */
function downloadWithPicks(array $fileIndexes, DownloadStatus $status = DownloadStatus::Downloading): array
{
    $download = Download::factory()->create(['hash' => IMPORT_HASH, 'status' => $status]);
    $picks = array_map(fn (int $index): DownloadPick => DownloadPick::factory()->for($download)->create([
        'file_index' => $index,
        'path' => "Batch/Show - {$index}.mkv",
        'episode_id' => Episode::factory()->create(['filename' => '', 'has_video_file' => false])->id,
    ]), $fileIndexes);

    return [$download, $picks];
}

/**
 * Fakes qBittorrent with the torrent in `$state` and its files at the given progress, by index.
 *
 * @param  array<int, float>  $progressByIndex
 */
function fakeTorrent(string $state, array $progressByIndex = []): void
{
    fakeQBittorrentApi([
        'torrents/info' => [['hash' => IMPORT_HASH, 'name' => 'Batch', 'state' => $state, 'progress' => 1, 'size' => 10]],
        'torrents/files' => collect($progressByIndex)
            ->map(fn (float $progress, int $index): array => ['index' => $index, 'name' => "Batch/Show - {$index}.mkv", 'size' => 10, 'progress' => $progress, 'priority' => 1])
            ->values()
            ->all(),
    ]);
}

test('the sync queues an import for each picked file that finished, and nothing for the rest', function () {
    [$download, [$done, $running]] = downloadWithPicks([0, 1]);
    fakeTorrent('downloading', [0 => 1.0, 1 => 0.4]);

    SyncDownloadsAction::run();

    Queue::assertPushed(ImportDownloadPickJob::class, 1);
    Queue::assertPushed(ImportDownloadPickJob::class, fn (ImportDownloadPickJob $job): bool => $job->pickId === $done->id);
    expect($download->fresh()->status)->toBe(DownloadStatus::Importing);
});

test('an import that fails at once keeps the download failed', function () {
    [$download] = downloadWithPicks([0]);
    fakeTorrent('downloading', [0 => 1.0]);
    // Runs during the dispatch, like a worker that is faster than the sync; its file is missing
    Queue::swap(Queue::getFacadeRoot()->queue);
    putVideoFile(videoBasePathForTests().'/mounted.mkv');

    SyncDownloadsAction::run();

    expect($download->fresh())
        ->status->toBe(DownloadStatus::Failed)
        ->error_code->toBe(DownloadErrorCode::FileMissing);
});

test('a download whose files are all in place completes, and goes once qBittorrent stopped seeding it', function () {
    [$download, [$pick]] = downloadWithPicks([0], DownloadStatus::Importing);
    $pick->update(['imported_at' => now()]);
    fakeTorrent('stoppedUP', [0 => 1.0]);

    SyncDownloadsAction::run();

    expect($download->fresh())
        ->status->toBe(DownloadStatus::Completed)
        ->client_removed_at->not->toBeNull()
        ->and(qBittorrentRequestsTo('torrents/delete'))->toBe([['hashes' => IMPORT_HASH, 'deleteFiles' => 'true']]);
});

test('a completed torrent that still seeds stays in qBittorrent', function () {
    [$download] = downloadWithPicks([0], DownloadStatus::Completed);
    fakeTorrent('uploading');

    SyncDownloadsAction::run();

    expect($download->fresh()->client_removed_at)->toBeNull()
        ->and(qBittorrentRequestsTo('torrents/delete'))->toBe([]);
});

test('a torrent gone before it finished fails the download, and after it is only cleaned up', function () {
    [$unfinished] = downloadWithPicks([0]);
    $finished = Download::factory()->create(['status' => DownloadStatus::Completed]);
    fakeQBittorrentApi(['torrents/info' => []]);

    SyncDownloadsAction::run();

    expect($unfinished->fresh())
        ->status->toBe(DownloadStatus::Failed)
        ->error_code->toBe(DownloadErrorCode::ClientLostTorrent)
        ->and($finished->fresh())
        ->status->toBe(DownloadStatus::Completed)
        ->client_removed_at->not->toBeNull();
});

test('a closed qBittorrent only waits for the next sync', function () {
    [$download] = downloadWithPicks([0]);
    Saloon::fake([QBittorrentConnector::class => saloonConnectionRefused()]);

    SyncDownloadsAction::run();

    expect($download->fresh()->status)->toBe(DownloadStatus::Downloading);
    Queue::assertNothingPushed();
});

test('an import copies the finished file into its episode and leaves the torrent its copy', function () {
    [$download, [$pick]] = downloadWithPicks([0], DownloadStatus::Importing);
    $source = putVideoFile(videoBasePathForTests().'/downloads/Batch/Show - 0.mkv', 'new-video');

    ImportDownloadPickAction::run($pick);

    $episode = $pick->episode->fresh();
    $target = ResolveEpisodeVideoPathAction::run($episode);

    expect($episode)
        ->filename->toBe('Show - 0.mkv')
        ->has_video_file->toBeTrue()
        ->video_file_size->toBe(9)
        ->video_duration_queued_at->not->toBeNull()
        ->and(File::get($target))->toBe('new-video')
        ->and(File::exists($source))->toBeTrue()
        ->and($pick->fresh()->imported_at)->not->toBeNull();
});

test('an import replaces a video the episode already had', function () {
    [, [$pick]] = downloadWithPicks([0], DownloadStatus::Importing);
    $episode = $pick->episode;
    // The path comes from the episode's name, the file name only gives its extension, so another one makes another path
    $episode->update(['filename' => 'Old release.avi', 'has_video_file' => true]);
    $oldPath = putVideoFile(ResolveEpisodeVideoPathAction::run($episode->fresh()), 'old-video');
    putVideoFile(videoBasePathForTests().'/downloads/Batch/Show - 0.mkv', 'new-video');

    ImportDownloadPickAction::run($pick);

    expect(File::exists($oldPath))->toBeFalse()
        ->and(File::get(ResolveEpisodeVideoPathAction::run($episode->fresh())))->toBe('new-video');
});

test('a file at the same path as the episode\'s video replaces it in place', function () {
    [, [$pick]] = downloadWithPicks([0], DownloadStatus::Importing);
    $episode = $pick->episode;
    $episode->update(['filename' => 'Old release.mkv', 'has_video_file' => true]);
    $path = putVideoFile(ResolveEpisodeVideoPathAction::run($episode->fresh()), 'old-video');
    putVideoFile(videoBasePathForTests().'/downloads/Batch/Show - 0.mkv', 'new-video');

    ImportDownloadPickAction::run($pick);

    expect(File::get($path))->toBe('new-video')
        ->and(File::glob("{$path}.replaced-*"))->toBe([]);
});

test('it says why an import failed', function (Closure $arrange, DownloadErrorCode $code) {
    [$download, [$pick]] = downloadWithPicks([0], DownloadStatus::Importing);
    $arrange();

    ImportDownloadPickAction::run($pick);

    expect($download->fresh())
        ->status->toBe(DownloadStatus::Failed)
        ->error_code->toBe($code)
        ->and($pick->fresh()->imported_at)->toBeNull()
        ->and($pick->episode->fresh()->filename)->toBe('');
})->with([
    'file gone from the download folder' => [fn () => null, DownloadErrorCode::FileMissing],
    'video disk full' => [function () {
        putVideoFile(videoBasePathForTests().'/downloads/Batch/Show - 0.mkv', 'new-video');
        ResolveFreeDiskSpaceAction::mock()->shouldReceive('handle')->andReturn(1024);
    }, DownloadErrorCode::NotEnoughDiskSpace],
]);

test('an import waits while the video folder moves', function () {
    [$download, [$pick]] = downloadWithPicks([0], DownloadStatus::Importing);
    putVideoFile(videoBasePathForTests().'/downloads/Batch/Show - 0.mkv', 'new-video');
    VideoRelocation::factory()->create();

    ImportDownloadPickAction::run($pick);

    expect($pick->fresh()->imported_at)->toBeNull()
        ->and($download->fresh()->status)->toBe(DownloadStatus::Importing);
});

test('a failed import can be tried again; a lost torrent can not', function () {
    $failed = Download::factory()->create(['status' => DownloadStatus::Failed, 'error_code' => DownloadErrorCode::NotEnoughDiskSpace]);
    $lost = Download::factory()->create(['status' => DownloadStatus::Failed, 'error_code' => DownloadErrorCode::ClientLostTorrent]);
    $running = Download::factory()->create(['status' => DownloadStatus::Downloading]);
    actingAsAdmin();

    postJson("/api/v1/downloads/{$failed->id}/retry")->assertNoContent();
    postJson("/api/v1/downloads/{$lost->id}/retry")->assertConflict()->assertJsonPath('code', 'download_not_retryable');
    postJson("/api/v1/downloads/{$running->id}/retry")->assertConflict();

    expect($failed->fresh())->status->toBe(DownloadStatus::Downloading)->error_code->toBeNull();
});

test('a completed download qBittorrent no longer has still reads as completed', function () {
    Download::factory()->create(['status' => DownloadStatus::Completed, 'client_removed_at' => now()]);
    fakeQBittorrentApi(['torrents/info' => []]);
    actingAsAdmin();

    Pest\Laravel\getJson('/api/v1/downloads')->assertJsonPath('0.status', 'completed')->assertJsonPath('0.error_code', null);
});
