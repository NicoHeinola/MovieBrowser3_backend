<?php

use App\Actions\Download\Import\ImportDownloadPickAction;
use App\Actions\Episode\VideoFile\Path\IsVideoBaseFolderAvailableAction;
use App\Actions\Episode\VideoFile\Path\ResolveEpisodeVideoPathAction;
use App\Actions\File\CopyFileAction;
use App\Actions\File\CreateFileLinkAction;
use App\Actions\File\ResolveDiskSpaceAction;
use App\Dtos\File\DiskSpaceData;
use App\Enums\DownloadErrorCode;
use App\Enums\DownloadStatus;
use App\Jobs\ImportDownloadPickJob;
use App\Jobs\SyncDownloadsJob;
use App\Models\Download\Download;
use App\Models\DownloadPick\DownloadPick;
use App\Models\Episode\Episode;
use App\Models\Setting\Setting;
use App\Models\ShowEntry\ShowEntry;
use App\Models\VideoBaseFolderMove\VideoBaseFolderMove;
use App\Services\QBittorrent\QBittorrentConnector;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

use function Pest\Laravel\postJson;

const IMPORT_HASH = '12c03cf9cc60ea7c13931e0195fa54903d8af85f';

beforeEach(function () {
    Saloon::fake([]);
    Queue::fake();

    foreach ([
        'qbittorrent_url' => 'http://qbittorrent.test',
        'qbittorrent_username' => 'admin',
        'qbittorrent_password' => 'secret',
        'download_path' => videoBaseFolderForTests().'/downloads',
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

/**
 * One run of the scheduled sync.
 */
function syncDownloads(): void
{
    app()->call([new SyncDownloadsJob, 'handle']);
}

test('the sync queues an import for each picked file that finished, and nothing for the rest', function () {
    [$download, [$done, $running]] = downloadWithPicks([0, 1]);
    fakeTorrent('downloading', [0 => 1.0, 1 => 0.4]);

    syncDownloads();

    Queue::assertPushed(ImportDownloadPickJob::class, 1);
    Queue::assertPushed(ImportDownloadPickJob::class, fn (ImportDownloadPickJob $job): bool => $job->pick->is($done));
    expect($download->fresh()->status)->toBe(DownloadStatus::Importing);
});

test('an import that fails at once keeps the download failed', function () {
    [$download] = downloadWithPicks([0]);
    fakeTorrent('downloading', [0 => 1.0]);
    // Runs during the dispatch, like a worker that is faster than the sync; its file is missing
    Queue::swap(Queue::getFacadeRoot()->queue);
    putVideoFile(videoBaseFolderForTests().'/mounted.mkv');

    syncDownloads();

    expect($download->fresh())
        ->status->toBe(DownloadStatus::Failed)
        ->error_code->toBe(DownloadErrorCode::FileMissing);
});

test('a download whose files are all in place completes, and goes once qBittorrent stopped seeding it', function () {
    [$download, [$pick]] = downloadWithPicks([0], DownloadStatus::Importing);
    $pick->update(['imported_at' => now()]);
    fakeTorrent('stoppedUP', [0 => 1.0]);

    syncDownloads();

    expect($download->fresh())
        ->status->toBe(DownloadStatus::Completed)
        ->client_removed_at->not->toBeNull()
        ->and(qBittorrentRequestsTo('torrents/delete'))->toBe([['hashes' => IMPORT_HASH, 'deleteFiles' => 'true']]);
});

test('a completed torrent that still seeds stays in qBittorrent', function () {
    [$download] = downloadWithPicks([0], DownloadStatus::Completed);
    fakeTorrent('uploading');

    syncDownloads();

    expect($download->fresh()->client_removed_at)->toBeNull()
        ->and(qBittorrentRequestsTo('torrents/delete'))->toBe([]);
});

test('a torrent gone before it finished fails the download, and after it is only cleaned up', function () {
    [$unfinished] = downloadWithPicks([0]);
    $finished = Download::factory()->create(['status' => DownloadStatus::Completed]);
    fakeQBittorrentApi(['torrents/info' => []]);

    syncDownloads();

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

    syncDownloads();

    expect($download->fresh()->status)->toBe(DownloadStatus::Downloading);
    Queue::assertNothingPushed();
});

test('an import copies the finished file into its episode and leaves the torrent its copy', function () {
    [$download, [$pick]] = downloadWithPicks([0], DownloadStatus::Importing);
    $video = matroskaVideo(1440);
    $source = putVideoFile(videoBaseFolderForTests().'/downloads/Batch/Show - 0.mkv', $video);

    ImportDownloadPickAction::run($pick);

    $episode = $pick->episode->fresh();
    $target = ResolveEpisodeVideoPathAction::run($episode);

    expect($episode)
        ->filename->toBe('Show - 0.mkv')
        ->has_video_file->toBeTrue()
        ->video_file_size->toBe(strlen($video))
        ->video_duration_seconds->toBe(1440)
        ->and(File::get($target))->toBe($video)
        ->and(File::exists($source))->toBeTrue()
        ->and($pick->fresh()->imported_at)->not->toBeNull();
});

test('an import replaces a video the episode already had', function () {
    [, [$pick]] = downloadWithPicks([0], DownloadStatus::Importing);
    $episode = $pick->episode;
    // The path comes from the episode's name, the file name only gives its extension, so another one makes another path
    $episode->update(['filename' => 'Old release.avi', 'has_video_file' => true]);
    $oldPath = putVideoFile(ResolveEpisodeVideoPathAction::run($episode->fresh()), 'old-video');
    putVideoFile(videoBaseFolderForTests().'/downloads/Batch/Show - 0.mkv', 'new-video');

    ImportDownloadPickAction::run($pick);

    expect(File::exists($oldPath))->toBeFalse()
        ->and(File::get(ResolveEpisodeVideoPathAction::run($episode->fresh())))->toBe('new-video');
});

test('a file at the same path as the episode\'s video replaces it in place', function () {
    [, [$pick]] = downloadWithPicks([0], DownloadStatus::Importing);
    $episode = $pick->episode;
    $episode->update(['filename' => 'Old release.mkv', 'has_video_file' => true]);
    $path = putVideoFile(ResolveEpisodeVideoPathAction::run($episode->fresh()), 'old-video');
    putVideoFile(videoBaseFolderForTests().'/downloads/Batch/Show - 0.mkv', 'new-video');

    ImportDownloadPickAction::run($pick);

    expect(File::get($path))->toBe('new-video')
        ->and(scandir(dirname($path)))->toBe(['.', '..', basename($path)]);
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
        putVideoFile(videoBaseFolderForTests().'/downloads/Batch/Show - 0.mkv', 'new-video');
        CreateFileLinkAction::mock()->shouldReceive('handle')->andReturn(false);
        ResolveDiskSpaceAction::mock()->shouldReceive('handle')->andReturn(new DiskSpaceData(freeBytes: 1024, totalBytes: 1024));
    }, DownloadErrorCode::NotEnoughDiskSpace],
]);

test('an import waits while the video base folder moves', function () {
    [$download, [$pick]] = downloadWithPicks([0], DownloadStatus::Importing);
    putVideoFile(videoBaseFolderForTests().'/downloads/Batch/Show - 0.mkv', 'new-video');
    VideoBaseFolderMove::factory()->create();

    ImportDownloadPickAction::run($pick);

    expect($pick->fresh()->imported_at)->toBeNull()
        ->and($download->fresh()->status)->toBe(DownloadStatus::Importing);
});

test('a failed import can be tried again', function () {
    [$download, [$pick]] = downloadWithPicks([0], DownloadStatus::Failed);
    $download->update(['error_code' => DownloadErrorCode::NotEnoughDiskSpace]);
    putVideoFile(videoBaseFolderForTests().'/downloads/Batch/Show - 0.mkv');
    fakeTorrent('stalledUP', [0 => 1.0]);
    actingAsAdmin();

    postJson("/api/v1/downloads/{$download->id}/retry")->assertNoContent();

    expect($download->fresh())->status->toBe(DownloadStatus::Downloading)->error_code->toBeNull()
        ->and(qBittorrentRequestsTo('torrents/start'))->toHaveCount(1)
        ->and(qBittorrentRequestsTo('torrents/recheck'))->toBe([['hashes' => IMPORT_HASH]]);
});

test('a torrent qBittorrent no longer has can not be tried again', function (array $attributes) {
    [$download] = downloadWithPicks([0]);
    $download->update($attributes);
    fakeQBittorrentApi();
    actingAsAdmin();

    postJson("/api/v1/downloads/{$download->id}/retry")->assertConflict()->assertJsonPath('code', 'download_not_in_client');
})->with([
    'lost' => [['status' => DownloadStatus::Failed, 'error_code' => DownloadErrorCode::ClientLostTorrent]],
    'removed after seeding' => [['status' => DownloadStatus::Completed, 'client_removed_at' => now()]],
]);

test('a torrent qBittorrent lost without the app knowing yet can not be tried again', function () {
    [$download] = downloadWithPicks([0]);
    fakeQBittorrentApi(['torrents/files' => MockResponse::make('', 404)]);
    actingAsAdmin();

    postJson("/api/v1/downloads/{$download->id}/retry")->assertConflict()->assertJsonPath('code', 'download_not_in_client');
});

test('trying again imports a video that is gone, and leaves the ones that are there', function () {
    [$download, [$gone, $there]] = downloadWithPicks([0, 1], DownloadStatus::Completed);
    foreach ([$gone, $there] as $pick) {
        $pick->update(['imported_at' => now()]);
        $pick->episode->update(['filename' => basename($pick->path), 'has_video_file' => true]);
    }
    putVideoFile(ResolveEpisodeVideoPathAction::run($there->episode->fresh()));
    putVideoFile(videoBaseFolderForTests().'/downloads/Batch/Show - 0.mkv');
    fakeTorrent('stalledUP', [0 => 1.0, 1 => 1.0]);
    actingAsAdmin();

    postJson("/api/v1/downloads/{$download->id}/retry")->assertNoContent();

    expect($gone->fresh()->imported_at)->toBeNull()
        ->and($there->fresh()->imported_at)->not->toBeNull()
        ->and($download->fresh()->status)->toBe(DownloadStatus::Downloading);
});

test('trying again changes nothing while every video is in place', function () {
    [$download, [$pick]] = downloadWithPicks([0], DownloadStatus::Completed);
    $pick->update(['imported_at' => now()]);
    $pick->episode->update(['filename' => basename($pick->path), 'has_video_file' => true]);
    putVideoFile(ResolveEpisodeVideoPathAction::run($pick->episode->fresh()));
    fakeTorrent('stalledUP', [0 => 1.0]);
    actingAsAdmin();

    postJson("/api/v1/downloads/{$download->id}/retry")->assertNoContent();

    expect($download->fresh()->status)->toBe(DownloadStatus::Completed)
        ->and(qBittorrentRequestsTo('torrents/start'))->toBe([]);
    Queue::assertNothingPushed();
});

test('an unmounted video drive makes no video look gone', function () {
    [$download, [$pick]] = downloadWithPicks([0], DownloadStatus::Completed);
    $pick->update(['imported_at' => now()]);
    $pick->episode->update(['filename' => basename($pick->path), 'has_video_file' => true]);
    fakeTorrent('stalledUP', [0 => 1.0]);
    actingAsAdmin();
    IsVideoBaseFolderAvailableAction::mock()->shouldReceive('handle')->andReturn(false);

    postJson("/api/v1/downloads/{$download->id}/retry")->assertNoContent();

    expect($pick->fresh()->imported_at)->not->toBeNull();
});

test('a retry qBittorrent turns down can be repeated', function () {
    [$download, [$gone]] = downloadWithPicks([0], DownloadStatus::Completed);
    $gone->update(['imported_at' => now()]);
    $gone->episode->update(['filename' => basename($gone->path), 'has_video_file' => true]);
    putVideoFile(videoBaseFolderForTests().'/mounted.mkv');
    fakeQBittorrentApi([
        'torrents/info' => [['hash' => IMPORT_HASH, 'state' => 'stalledUP', 'progress' => 1, 'size' => 10]],
        'torrents/files' => [['index' => 0, 'name' => 'Batch/Show - 0.mkv', 'size' => 10, 'progress' => 1]],
        'torrents/filePrio' => MockResponse::make('boom', 500),
    ]);
    actingAsAdmin();

    postJson("/api/v1/downloads/{$download->id}/retry")->assertStatus(502);

    expect($download->fresh()->status)->toBe(DownloadStatus::Completed);

    fakeTorrent('stalledUP', [0 => 1.0]);
    postJson("/api/v1/downloads/{$download->id}/retry")->assertNoContent();

    expect($download->fresh()->status)->toBe(DownloadStatus::Downloading);
});

test('a failed download whose files all made it can be tried again', function () {
    [$download, [$pick]] = downloadWithPicks([0], DownloadStatus::Failed);
    $pick->update(['imported_at' => now()]);
    $pick->episode->update(['filename' => basename($pick->path), 'has_video_file' => true]);
    putVideoFile(ResolveEpisodeVideoPathAction::run($pick->episode->fresh()));
    fakeTorrent('stalledUP', [0 => 1.0]);
    actingAsAdmin();

    postJson("/api/v1/downloads/{$download->id}/retry")->assertNoContent();
    syncDownloads();

    expect($download->fresh()->status)->toBe(DownloadStatus::Completed);
});

test('nothing imports while qBittorrent checks the files', function () {
    [$download] = downloadWithPicks([0], DownloadStatus::Importing);
    fakeTorrent('checkingUP', [0 => 1.0]);

    syncDownloads();

    Queue::assertNothingPushed();
    expect($download->fresh()->status)->toBe(DownloadStatus::Importing);
});

test('a big batch queues each file once, apart from everything else', function () {
    foreach (range(1, 6) as $number) {
        $download = Download::factory()->create(['hash' => str_repeat((string) $number, 40), 'status' => DownloadStatus::Downloading]);
        foreach (range(0, 24) as $index) {
            DownloadPick::factory()->for($download)->create(['file_index' => $index, 'path' => "Batch {$number}/{$index}.mkv"]);
        }
    }
    fakeQBittorrentApi([
        'torrents/info' => Download::query()->pluck('hash')->map(fn (string $hash): array => ['hash' => $hash, 'state' => 'uploading', 'progress' => 1, 'name' => 'x', 'size' => 1])->all(),
        'torrents/files' => collect(range(0, 24))->map(fn (int $index): array => ['index' => $index, 'name' => "{$index}.mkv", 'size' => 10, 'progress' => 1, 'name' => 'x', 'size' => 1])->all(),
    ]);

    // Minutes of syncs while the first copies still run
    foreach (range(1, 5) as $ignored) {
        syncDownloads();
    }

    Queue::assertPushed(ImportDownloadPickJob::class, 150);
    Queue::assertPushedOn('imports', ImportDownloadPickJob::class);
});

test('one file that fails does not hold back the others', function () {
    [$download, [$missing, $fine]] = downloadWithPicks([0, 1], DownloadStatus::Importing);
    putVideoFile(videoBaseFolderForTests().'/downloads/Batch/Show - 1.mkv', 'new-video');

    ImportDownloadPickAction::run($missing);
    ImportDownloadPickAction::run($fine);

    expect($missing->fresh()->imported_at)->toBeNull()
        ->and($fine->fresh()->imported_at)->not->toBeNull()
        ->and($download->fresh()->status)->toBe(DownloadStatus::Failed);
});

test('an import that was killed starts over', function (string $leftover) {
    [, [$pick]] = downloadWithPicks([0], DownloadStatus::Importing);
    putVideoFile(videoBaseFolderForTests().'/downloads/Batch/Show - 0.mkv', 'new-video');
    $target = ResolveEpisodeVideoPathAction::run($pick->episode, filename: 'x.mkv');
    putVideoFile(dirname($target)."/{$pick->episode_id}.{$leftover}", 'new');

    ImportDownloadPickAction::run($pick);

    expect($pick->fresh()->imported_at)->not->toBeNull()
        ->and(File::get($target))->toBe('new-video')
        ->and(scandir(dirname($target)))->toBe(['.', '..', basename($target)]);
})->with(['halfway through the copy' => 'importing.part', 'after the copy' => 'importing']);

test('a failed copy leaves the video the episode had', function () {
    [, [$pick]] = downloadWithPicks([0], DownloadStatus::Importing);
    $pick->episode->update(['filename' => 'Old.mkv']);
    $target = putVideoFile(ResolveEpisodeVideoPathAction::run($pick->episode), 'old');
    putVideoFile(videoBaseFolderForTests().'/downloads/Batch/Show - 0.mkv', 'new-video');
    CopyFileAction::mock()->shouldReceive('handle')->andThrow(new RuntimeException('drive gone'));

    ImportDownloadPickAction::run($pick);

    expect(File::get($target))->toBe('old')
        ->and($pick->fresh()->imported_at)->toBeNull();
});

test('a folder move that starts during a copy makes the import wait', function () {
    [$download, [$pick]] = downloadWithPicks([0], DownloadStatus::Importing);
    putVideoFile(videoBaseFolderForTests().'/downloads/Batch/Show - 0.mkv', 'new-video');
    CopyFileAction::mock()->shouldReceive('handle')->andReturnUsing(function (): int {
        VideoBaseFolderMove::factory()->create();

        return 9;
    });

    ImportDownloadPickAction::run($pick);

    expect($pick->fresh()->imported_at)->toBeNull()
        ->and($pick->episode->fresh()->filename)->toBe('')
        ->and($download->fresh()->status)->toBe(DownloadStatus::Importing);
});

test('an episode deleted during the copy leaves nothing behind', function () {
    [$download, [$pick]] = downloadWithPicks([0], DownloadStatus::Importing);
    putVideoFile(videoBaseFolderForTests().'/downloads/Batch/Show - 0.mkv', 'new-video');
    $directory = dirname(ResolveEpisodeVideoPathAction::run($pick->episode, filename: 'x.mkv'));
    CopyFileAction::mock()->shouldReceive('handle')->andReturnUsing(function (string $from, string $to) use ($pick): int {
        Episode::query()->find($pick->episode_id)->delete();
        putVideoFile($to, 'new-video');

        return 9;
    });

    ImportDownloadPickAction::run($pick);

    expect(File::isDirectory($directory))->toBeFalse()
        ->and($download->fresh()->status)->toBe(DownloadStatus::Importing);
});

test('an episode renamed or moved during the copy gets its video where it is now', function () {
    [, [$pick]] = downloadWithPicks([0], DownloadStatus::Importing);
    $episode = $pick->episode;
    $otherEntry = ShowEntry::factory()->for($episode->entry->show)->create(['name' => 'Movie']);
    putVideoFile(videoBaseFolderForTests().'/downloads/Batch/Show - 0.mkv', 'new-video');
    $oldDirectory = dirname(ResolveEpisodeVideoPathAction::run($episode, filename: 'x.mkv'));
    CopyFileAction::mock()->shouldReceive('handle')->andReturnUsing(function (string $from, string $to) use ($episode, $otherEntry): int {
        Episode::query()->find($episode->id)->update(['name' => 'Renamed', 'show_entry_id' => $otherEntry->id]);
        putVideoFile($to, 'new-video');

        return 9;
    });

    ImportDownloadPickAction::run($pick);

    expect(File::get(ResolveEpisodeVideoPathAction::run($episode->fresh())))->toBe('new-video')
        ->and($episode->fresh()->name)->toBe('Renamed')
        ->and(File::isDirectory($oldDirectory))->toBeFalse();
});

test('a completed download qBittorrent no longer has still reads as completed', function () {
    Download::factory()->create(['status' => DownloadStatus::Completed, 'client_removed_at' => now()]);
    fakeQBittorrentApi(['torrents/info' => []]);
    actingAsAdmin();

    Pest\Laravel\getJson('/api/v1/downloads')->assertJsonPath('0.status', 'completed')->assertJsonPath('0.error_code', null);
});
