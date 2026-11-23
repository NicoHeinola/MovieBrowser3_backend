<?php

use App\Actions\Download\ResolveDownloadPathAction;
use App\Enums\DownloadStatus;
use App\Models\Download\Download;
use App\Models\DownloadPick\DownloadPick;
use App\Models\Episode\Episode;
use App\Models\Setting\Setting;
use App\Models\Show\Show;
use App\Models\ShowEntry\ShowEntry;
use App\Models\User\User;
use App\Services\QBittorrent\QBittorrentConnector;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;
use function Pest\Laravel\withToken;

const DOWNLOAD_HASH = '12c03cf9cc60ea7c13931e0195fa54903d8af85f';
const DOWNLOAD_MAGNET = 'magnet:?xt=urn:btih:12c03cf9cc60ea7c13931e0195fa54903d8af85f&dn=Mushoku';

beforeEach(function () {
    // Faked with no answers, so any request a test doesn't expect fails it
    Saloon::fake([]);
    Sleep::fake();
    config(['deploy.media_folders' => []]);

    foreach ([
        'qbittorrent_url' => 'http://qbittorrent.test',
        'qbittorrent_username' => 'admin',
        'qbittorrent_password' => 'secret',
        'download_path' => '/mnt/d/MovieBrowser3/Downloads',
    ] as $key => $value) {
        Setting::query()->find($key)->update(['value' => $value]);
    }
});

/**
 * A saved qBittorrent answer from `tests/Fixtures/qbittorrent`, optionally changed before it's sent.
 *
 * @param  callable(array<int, mixed>): array<int, mixed>|null  $change
 * @return array<int, mixed>
 */
function qBittorrentAnswer(string $name, ?callable $change = null): array
{
    $answer = json_decode(file_get_contents(base_path("tests/Fixtures/qbittorrent/{$name}.json")), true);

    return $change !== null ? $change($answer) : $answer;
}

/**
 * A show with one entry of two episodes.
 *
 * @return array{Show, Episode, Episode}
 */
function showWithTwoEpisodes(): array
{
    $show = Show::factory()->create();
    $entry = ShowEntry::factory()->for($show)->create();

    return [$show, Episode::factory()->for($entry, 'entry')->create(), Episode::factory()->for($entry, 'entry')->create()];
}

test('downloads require an admin', function () {
    $download = Download::factory()->create();

    postJson('/api/v1/downloads', [])->assertUnauthorized();
    getJson("/api/v1/downloads/{$download->id}")->assertUnauthorized();

    withToken(User::factory()->create()->createToken('test-token')->plainTextToken);

    postJson('/api/v1/downloads', [])->assertForbidden();
    getJson("/api/v1/downloads/{$download->id}")->assertForbidden();
    putJson("/api/v1/downloads/{$download->id}/files", [])->assertForbidden();
    Saloon::assertNothingSent();
});

test('a torrent is added stopped to the download folder, as Windows sees it', function () {
    fakeQBittorrentApi([
        'torrents/info' => qBittorrentAnswer('torrents-info', fn (array $answer) => [[...$answer[0], 'state' => 'stoppedDL', 'progress' => 0, 'downloaded' => 0]]),
        'torrents/files' => qBittorrentAnswer('torrents-files'),
    ]);
    actingAsAdmin();

    postJson('/api/v1/downloads', ['magnet_url' => DOWNLOAD_MAGNET, 'torrent_url' => 'https://nekobt.test/api/v1/torrents/1/download', 'name' => 'Mushoku Tensei S01'])
        ->assertCreated()
        ->assertJsonPath('status', 'waiting_for_files')
        ->assertJsonPath('hash', DOWNLOAD_HASH)
        ->assertJsonPath('name', 'Mushoku Tensei S01')
        ->assertJsonPath('show', null)
        ->assertJsonCount(3, 'files')
        ->assertJsonPath('files.2', [
            'index' => 2,
            'path' => '[anonymous] Mushoku Tensei (2021) S01/Extras/NCOP1.mkv',
            'size_bytes' => 300000000,
            'progress' => 0,
            'episode_id' => null,
            'show_id' => null,
            'imported_at' => null,
        ]);

    $added = qBittorrentRequestsTo('torrents/add');
    expect($added)->toHaveCount(1)
        ->and($added[0])->toBe([
            'urls' => 'https://nekobt.test/api/v1/torrents/1/download',
            'savepath' => 'D:\\MovieBrowser3\\Downloads',
            'category' => 'moviebrowser3',
            'stopped' => 'true',
            'stopCondition' => 'None',
        ]);
    expect(Download::query()->sole())->status->toBe(DownloadStatus::WaitingForFiles);
});

test('a magnet runs until qBittorrent has its files, and reports fetching them until then', function () {
    fakeQBittorrentApi([
        'torrents/info' => qBittorrentAnswer('torrents-info', fn (array $answer) => [[...$answer[0], 'has_metadata' => false]]),
        'torrents/files' => [],
    ]);
    actingAsAdmin();

    postJson('/api/v1/downloads', ['magnet_url' => DOWNLOAD_MAGNET, 'name' => 'Mushoku'])
        ->assertCreated()
        ->assertJsonPath('status', 'fetching_metadata')
        ->assertJsonPath('files', []);

    expect(collect(qBittorrentRequestsTo('torrents/add')[0])->only(['urls', 'stopped', 'stopCondition'])->all())
        ->toBe(['urls' => DOWNLOAD_MAGNET, 'stopped' => 'false', 'stopCondition' => 'MetadataReceived']);
});

test('it waits for qBittorrent to list a torrent it is still reading', function () {
    $lookups = 0;
    fakeQBittorrentApi([
        'torrents/info' => function () use (&$lookups) {
            return MockResponse::make(++$lookups < 3 ? [] : qBittorrentAnswer('torrents-info'));
        },
        'torrents/files' => qBittorrentAnswer('torrents-files'),
    ]);
    actingAsAdmin();

    postJson('/api/v1/downloads', ['magnet_url' => DOWNLOAD_MAGNET, 'name' => 'Mushoku'])->assertCreated();

    Sleep::assertSleptTimes(2);
});

test('a torrent qBittorrent never lists is turned down and not saved', function () {
    config(['qbittorrent.add_wait_attempts' => 3]);
    fakeQBittorrentApi(['torrents/info' => []]);
    actingAsAdmin();

    postJson('/api/v1/downloads', ['magnet_url' => DOWNLOAD_MAGNET, 'name' => 'Mushoku'])
        ->assertStatus(502)
        ->assertExactJson(['code' => 'qbittorrent_unavailable', 'params' => ['reason' => 'torrent_rejected']]);

    expect(Download::query()->count())->toBe(0);
});

test('a torrent qBittorrent already has is taken over', function () {
    fakeQBittorrentApi([
        'torrents/add' => 'Fails.',
        'torrents/info' => qBittorrentAnswer('torrents-info'),
        'torrents/files' => qBittorrentAnswer('torrents-files'),
    ]);
    actingAsAdmin();

    postJson('/api/v1/downloads', ['magnet_url' => DOWNLOAD_MAGNET, 'name' => 'Mushoku'])->assertCreated();

    Sleep::assertNeverSlept();
});

test('a torrent that is already a download answers with its id', function () {
    $download = Download::factory()->create(['hash' => DOWNLOAD_HASH]);
    actingAsAdmin();

    postJson('/api/v1/downloads', ['magnet_url' => DOWNLOAD_MAGNET, 'name' => 'Mushoku'])
        ->assertConflict()
        ->assertExactJson(['code' => 'download_exists', 'params' => ['download_id' => (string) $download->id]]);
    Saloon::assertNothingSent();
});

test('base32 info hashes find the same torrent', function () {
    Download::factory()->create(['hash' => DOWNLOAD_HASH]);
    actingAsAdmin();

    postJson('/api/v1/downloads', ['magnet_url' => 'magnet:?xt=urn:btih:CLADZ6OMMDVHYE4TDYAZL6SUSA6YV6C7XY&dn=x', 'name' => 'x'])->assertUnprocessable();
    postJson('/api/v1/downloads', ['magnet_url' => 'magnet:?xt=urn:btih:cladz6ommdvhye4tdyazl6susa6yv6c7', 'name' => 'x'])->assertConflict();
});

test('downloads are off until qBittorrent and the folder are set', function (string $key) {
    Setting::query()->find($key)->update(['value' => '']);
    actingAsAdmin();

    postJson('/api/v1/downloads', ['magnet_url' => DOWNLOAD_MAGNET, 'name' => 'Mushoku'])
        ->assertConflict()
        ->assertExactJson(['code' => 'download_client_not_configured', 'params' => []]);
    Saloon::assertNothingSent();
})->with(['qbittorrent_url', 'download_path']);

test('adding a download needs a magnet with an info hash', function (array $body, string $field) {
    actingAsAdmin();

    postJson('/api/v1/downloads', $body)->assertUnprocessable()->assertJsonValidationErrors([$field]);
})->with([
    'no magnet' => [['name' => 'x'], 'magnet_url'],
    'not a magnet' => [['magnet_url' => 'https://example.test', 'name' => 'x'], 'magnet_url'],
    'no hash' => [['magnet_url' => 'magnet:?dn=x', 'name' => 'x'], 'magnet_url'],
    'bad torrent link' => [['magnet_url' => DOWNLOAD_MAGNET, 'torrent_url' => 'ftp://x', 'name' => 'x'], 'torrent_url'],
    'a name that is not text' => [['magnet_url' => DOWNLOAD_MAGNET, 'name' => ['x']], 'name'],
]);

test('a download reports its progress and picked files live', function () {
    [$show, $episode] = showWithTwoEpisodes();
    $show->titles()->create(['title' => 'Mushoku Tensei', 'is_primary' => true]);
    $download = Download::factory()->create(['hash' => DOWNLOAD_HASH, 'show_id' => $show->id, 'status' => DownloadStatus::Downloading]);
    DownloadPick::factory()->for($download)->create(['file_index' => 0, 'episode_id' => $episode->id, 'imported_at' => '2026-11-23 12:00:00']);
    fakeQBittorrentApi(['torrents/info' => qBittorrentAnswer('torrents-info'), 'torrents/files' => qBittorrentAnswer('torrents-files')]);
    actingAsAdmin();

    getJson("/api/v1/downloads/{$download->id}")
        ->assertOk()
        ->assertJsonPath('status', 'downloading')
        ->assertJsonPath('error_code', null)
        ->assertJsonPath('progress', 0.25)
        ->assertJsonPath('size_bytes', 4000000000)
        ->assertJsonPath('downloaded_bytes', 1000000000)
        ->assertJsonPath('download_speed', 5000000)
        ->assertJsonPath('eta_seconds', 600)
        ->assertJsonPath('show.id', $show->id)
        ->assertJsonPath('show.titles.0.title', 'Mushoku Tensei')
        ->assertJsonMissingPath('show.entries')
        ->assertJsonPath('files.0.episode_id', $episode->id)
        ->assertJsonPath('files.0.show_id', $show->id)
        ->assertJsonPath('files.0.progress', 0.5)
        ->assertJsonPath('files.0.imported_at', '2026-11-23T12:00:00.000000Z')
        ->assertJsonPath('files.1.episode_id', null)
        ->assertJsonPath('files.1.imported_at', null);
});

test('an unknown ETA is null', function () {
    $download = Download::factory()->create(['hash' => DOWNLOAD_HASH]);
    fakeQBittorrentApi([
        'torrents/info' => qBittorrentAnswer('torrents-info', fn (array $answer) => [[...$answer[0], 'eta' => 8640000]]),
        'torrents/files' => [],
    ]);
    actingAsAdmin();

    getJson("/api/v1/downloads/{$download->id}")->assertJsonPath('eta_seconds', null);
});

test('a download qBittorrent lost reads as failed without changing it', function () {
    $download = Download::factory()->create(['hash' => DOWNLOAD_HASH, 'status' => DownloadStatus::Downloading]);
    fakeQBittorrentApi(['torrents/info' => []]);
    actingAsAdmin();

    getJson("/api/v1/downloads/{$download->id}")
        ->assertOk()
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('error_code', 'client_lost_torrent')
        ->assertJsonPath('files', []);

    expect($download->fresh()->status)->toBe(DownloadStatus::Downloading);
});

test('a closed qBittorrent says so', function () {
    $download = Download::factory()->create();
    Saloon::fake([QBittorrentConnector::class => saloonConnectionRefused()]);
    actingAsAdmin();

    getJson("/api/v1/downloads/{$download->id}")
        ->assertStatus(502)
        ->assertExactJson(['code' => 'qbittorrent_unavailable', 'params' => ['reason' => 'unreachable']]);
});

test('picking files links them to episodes, downloads only them and starts the torrent', function () {
    [$show, $first, $second] = showWithTwoEpisodes();
    $download = Download::factory()->create(['hash' => DOWNLOAD_HASH]);
    fakeQBittorrentApi(['torrents/info' => qBittorrentAnswer('torrents-info'), 'torrents/files' => qBittorrentAnswer('torrents-files')]);
    actingAsAdmin();

    putJson("/api/v1/downloads/{$download->id}/files", ['show_id' => $show->id, 'files' => [
        ['index' => 0, 'episode_id' => $first->id],
        ['index' => 1, 'episode_id' => $second->id],
    ]])
        ->assertOk()
        ->assertJsonPath('status', 'downloading')
        ->assertJsonPath('show.id', $show->id)
        ->assertJsonPath('files.0.episode_id', $first->id)
        ->assertJsonPath('files.1.episode_id', $second->id)
        ->assertJsonPath('files.2.episode_id', null);

    expect(qBittorrentRequestsTo('torrents/filePrio'))->toBe([
        ['hash' => DOWNLOAD_HASH, 'id' => '2', 'priority' => '0'],
        ['hash' => DOWNLOAD_HASH, 'id' => '0|1', 'priority' => '1'],
    ])
        ->and(qBittorrentRequestsTo('torrents/start'))->toBe([['hashes' => DOWNLOAD_HASH]])
        ->and(DownloadPick::query()->where('download_id', $download->id)->orderBy('file_index')->get(['file_index', 'path', 'size_bytes', 'episode_id'])->toArray())->toBe([
            ['file_index' => 0, 'path' => '[anonymous] Mushoku Tensei (2021) S01/[anonymous] Mushoku Tensei S01E01 [1715F359].mkv', 'size_bytes' => 2000000000, 'episode_id' => $first->id],
            ['file_index' => 1, 'path' => '[anonymous] Mushoku Tensei (2021) S01/[anonymous] Mushoku Tensei S01E02 [0225665E].mkv', 'size_bytes' => 2000000000, 'episode_id' => $second->id],
        ]);
});

test('picking again replaces the links, and picking nothing stops the torrent', function () {
    [$show, $first, $second] = showWithTwoEpisodes();
    $download = Download::factory()->create(['hash' => DOWNLOAD_HASH, 'show_id' => $show->id, 'status' => DownloadStatus::Downloading]);
    DownloadPick::factory()->for($download)->create(['file_index' => 0, 'episode_id' => $first->id]);
    fakeQBittorrentApi(['torrents/info' => qBittorrentAnswer('torrents-info'), 'torrents/files' => qBittorrentAnswer('torrents-files')]);
    actingAsAdmin();

    // The episode moves to another file of the same torrent
    putJson("/api/v1/downloads/{$download->id}/files", ['show_id' => $show->id, 'files' => [['index' => 1, 'episode_id' => $first->id]]])->assertOk();
    expect(DownloadPick::query()->pluck('episode_id', 'file_index')->all())->toBe([1 => $first->id]);

    putJson("/api/v1/downloads/{$download->id}/files", ['show_id' => $show->id, 'files' => []])
        ->assertOk()
        ->assertJsonPath('status', 'waiting_for_files');

    expect(DownloadPick::query()->count())->toBe(0)
        ->and(qBittorrentRequestsTo('torrents/stop'))->toBe([['hashes' => DOWNLOAD_HASH]])
        ->and(collect(qBittorrentRequestsTo('torrents/filePrio'))->last())->toBe(['hash' => DOWNLOAD_HASH, 'id' => '0|1|2', 'priority' => '0']);
});

test('it says which picked file or episode does not fit', function (Closure $files, string $field, string $code) {
    [$show, $first] = showWithTwoEpisodes();
    [, $otherShowEpisode] = showWithTwoEpisodes();
    $busyEpisode = DownloadPick::factory()->create()->episode;
    $busyEpisode->entry()->associate($first->entry)->save();
    $download = Download::factory()->create(['hash' => DOWNLOAD_HASH]);
    fakeQBittorrentApi(['torrents/info' => qBittorrentAnswer('torrents-info'), 'torrents/files' => qBittorrentAnswer('torrents-files')]);
    actingAsAdmin();

    $response = putJson("/api/v1/downloads/{$download->id}/files", ['show_id' => $show->id, 'files' => $files($first, $otherShowEpisode, $busyEpisode)])
        ->assertUnprocessable();

    // Field names hold dots, so they're read as keys, not as a JSON path
    expect($response->json('errors')[$field][0]['code'])->toBe($code);

    expect(qBittorrentRequestsTo('torrents/start'))->toBe([]);
})->with([
    'no such file' => [fn (Episode $first) => [['index' => 9, 'episode_id' => $first->id]], 'files.0.index', 'file_index_invalid'],
    'another show' => [fn (Episode $first, Episode $other) => [['index' => 0, 'episode_id' => $other->id]], 'files.0.episode_id', 'episode_not_in_show'],
    'waits for another download' => [fn (Episode $first, Episode $other, Episode $busy) => [['index' => 0, 'episode_id' => $busy->id]], 'files.0.episode_id', 'episode_already_downloading'],
    'same file twice' => [fn (Episode $first, Episode $other) => [['index' => 0, 'episode_id' => $first->id], ['index' => 0, 'episode_id' => $other->id]], 'files.0.index', 'distinct'],
]);

test('picked files must fit on the download disk', function () {
    [$show, $first, $second] = showWithTwoEpisodes();
    $download = Download::factory()->create(['hash' => DOWNLOAD_HASH]);
    // The disk is measured at the download folder's nearest existing parent: this test's own, nearly empty space
    Setting::query()->find('download_path')->update(['value' => videoBasePathForTests()]);
    $free = (int) disk_free_space(dirname(videoBasePathForTests()));
    fakeQBittorrentApi([
        'torrents/info' => qBittorrentAnswer('torrents-info'),
        'torrents/files' => qBittorrentAnswer('torrents-files', fn (array $files) => [[...$files[0], 'size' => $free * 4, 'progress' => 0.5]]),
    ]);
    actingAsAdmin();

    putJson("/api/v1/downloads/{$download->id}/files", ['show_id' => $show->id, 'files' => [['index' => 0, 'episode_id' => $first->id]]])
        ->assertUnprocessable()
        ->assertJsonPath('errors.files.0.code', 'not_enough_disk_space')
        ->assertJsonPath('errors.files.0.params.needed_bytes', (string) ($free * 2));
});

test('picking files of a torrent qBittorrent lost says so', function () {
    [$show, $first] = showWithTwoEpisodes();
    $download = Download::factory()->create(['hash' => DOWNLOAD_HASH]);
    fakeQBittorrentApi(['torrents/files' => MockResponse::make('Not Found', 404)]);
    actingAsAdmin();

    putJson("/api/v1/downloads/{$download->id}/files", ['show_id' => $show->id, 'files' => [['index' => 0, 'episode_id' => $first->id]]])
        ->assertConflict()
        ->assertExactJson(['code' => 'download_not_in_client', 'params' => []]);
});

test('picking files needs a show and well-formed files', function (array $body, string $field) {
    $download = Download::factory()->create();
    actingAsAdmin();

    putJson("/api/v1/downloads/{$download->id}/files", $body)->assertUnprocessable()->assertJsonValidationErrors([$field]);
    Saloon::assertNothingSent();
})->with([
    'no show' => [['files' => []], 'show_id'],
    'unknown show' => [['show_id' => 999, 'files' => []], 'show_id'],
    'no files key' => [['show_id' => 1], 'files'],
    'unknown episode' => [['show_id' => 1, 'files' => [['index' => 0, 'episode_id' => 999]]], 'files.0.episode_id'],
]);

test('a show lists which download each episode waits for', function () {
    [$show, $first, $second] = showWithTwoEpisodes();
    $download = Download::factory()->create(['show_id' => $show->id, 'status' => DownloadStatus::Downloading]);
    DownloadPick::factory()->for($download)->create(['file_index' => 3, 'path' => 'Batch/E01.mkv', 'episode_id' => $first->id]);
    actingAsAdmin();

    $episodes = collect(getJson("/api/v1/shows/{$show->id}")->assertOk()->json('entries.0.episodes'))->keyBy('id');

    expect($episodes[$first->id]['download'])->toBe(['id' => $download->id, 'file_index' => 3, 'path' => 'Batch/E01.mkv', 'status' => 'downloading', 'imported_at' => null])
        ->and($episodes[$second->id]['download'])->toBeNull();
});

test('deleting an episode drops its link but keeps the download', function () {
    [, $first] = showWithTwoEpisodes();
    $link = DownloadPick::factory()->create(['episode_id' => $first->id]);

    $first->delete();

    expect(DownloadPick::query()->find($link->id))->toBeNull()
        ->and(Download::query()->find($link->download_id))->not->toBeNull();
});

test('qBittorrent gets the download folder the way it sees it', function (string $path, string $forClient) {
    Setting::query()->find('download_path')->update(['value' => $path]);

    expect(ResolveDownloadPathAction::run(forClient: true))->toBe($forClient)
        ->and(ResolveDownloadPathAction::run())->toBe(rtrim($path, '/'));
})->with([
    'a WSL drive mount' => ['/mnt/d/MovieBrowser3/Downloads', 'D:\\MovieBrowser3\\Downloads'],
    'a drive root' => ['/mnt/e/', 'E:\\'],
    'a Linux folder' => ['/srv/downloads', '/srv/downloads'],
]);

test('deleting a show removes its downloads from qBittorrent, files and all', function () {
    [$show, $first, $second] = showWithTwoEpisodes();
    $download = Download::factory()->create(['hash' => DOWNLOAD_HASH, 'show_id' => $show->id, 'status' => DownloadStatus::Downloading]);
    DownloadPick::factory()->for($download)->create(['file_index' => 0, 'episode_id' => $first->id]);
    DownloadPick::factory()->for($download)->create(['file_index' => 1, 'episode_id' => $second->id]);
    fakeQBittorrentApi();
    actingAsAdmin();

    deleteJson("/api/v1/shows/{$show->id}")->assertNoContent();

    expect(Download::query()->count())->toBe(0)
        ->and(qBittorrentRequestsTo('torrents/delete'))->toBe([['hashes' => DOWNLOAD_HASH, 'deleteFiles' => 'true']]);
});

test('deleting an episode stops downloading its file, and the rest keep going', function () {
    [$show, $first, $second] = showWithTwoEpisodes();
    $download = Download::factory()->create(['hash' => DOWNLOAD_HASH, 'show_id' => $show->id, 'status' => DownloadStatus::Downloading]);
    DownloadPick::factory()->for($download)->create(['file_index' => 0, 'episode_id' => $first->id]);
    DownloadPick::factory()->for($download)->create(['file_index' => 1, 'episode_id' => $second->id]);
    fakeQBittorrentApi(['torrents/files' => qBittorrentAnswer('torrents-files')]);
    actingAsAdmin();

    deleteJson("/api/v1/episodes/{$first->id}")->assertNoContent();

    expect(qBittorrentRequestsTo('torrents/filePrio'))->toBe([
        ['hash' => DOWNLOAD_HASH, 'id' => '0|2', 'priority' => '0'],
        ['hash' => DOWNLOAD_HASH, 'id' => '1', 'priority' => '1'],
    ])
        ->and(qBittorrentRequestsTo('torrents/start'))->toBe([['hashes' => DOWNLOAD_HASH]])
        ->and($download->fresh()->status)->toBe(DownloadStatus::Downloading);
});

test('deleting the last picked episode stops the torrent, which waits for new picks', function () {
    [$show, $first] = showWithTwoEpisodes();
    $download = Download::factory()->create(['hash' => DOWNLOAD_HASH, 'show_id' => $show->id, 'status' => DownloadStatus::Downloading]);
    DownloadPick::factory()->for($download)->create(['file_index' => 0, 'episode_id' => $first->id]);
    fakeQBittorrentApi(['torrents/files' => qBittorrentAnswer('torrents-files')]);
    actingAsAdmin();

    deleteJson("/api/v1/episodes/{$first->id}")->assertNoContent();

    expect(qBittorrentRequestsTo('torrents/stop'))->toBe([['hashes' => DOWNLOAD_HASH]])
        ->and($download->fresh()->status)->toBe(DownloadStatus::WaitingForFiles);
});

test('a closed qBittorrent does not stop a show from being deleted', function () {
    [$show, $first] = showWithTwoEpisodes();
    $download = Download::factory()->create(['hash' => DOWNLOAD_HASH, 'show_id' => $show->id, 'status' => DownloadStatus::Downloading]);
    DownloadPick::factory()->for($download)->create(['file_index' => 0, 'episode_id' => $first->id]);
    Saloon::fake([QBittorrentConnector::class => saloonConnectionRefused()]);
    Log::spy();
    actingAsAdmin();

    deleteJson("/api/v1/shows/{$show->id}")->assertNoContent();

    expect(Show::query()->find($show->id))->toBeNull()
        ->and(Download::query()->find($download->id))->not->toBeNull();
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'deleted episodes'));
});

test('deleting a show leaves downloads it never picked from alone', function () {
    [$show] = showWithTwoEpisodes();
    $download = Download::factory()->create(['hash' => DOWNLOAD_HASH]);
    actingAsAdmin();

    deleteJson("/api/v1/shows/{$show->id}")->assertNoContent();

    expect(Download::query()->find($download->id))->not->toBeNull();
    Saloon::assertNothingSent();
});

test('one torrent can feed two shows: each show replaces only its own picks', function () {
    [$firstShow, $firstShowEpisode] = showWithTwoEpisodes();
    [$secondShow, $secondShowEpisode] = showWithTwoEpisodes();
    $download = Download::factory()->create(['hash' => DOWNLOAD_HASH, 'show_id' => $firstShow->id, 'status' => DownloadStatus::Downloading]);
    DownloadPick::factory()->for($download)->create(['file_index' => 0, 'episode_id' => $firstShowEpisode->id]);
    fakeQBittorrentApi(['torrents/info' => qBittorrentAnswer('torrents-info'), 'torrents/files' => qBittorrentAnswer('torrents-files')]);
    actingAsAdmin();

    putJson("/api/v1/downloads/{$download->id}/files", ['show_id' => $secondShow->id, 'files' => [['index' => 1, 'episode_id' => $secondShowEpisode->id]]])
        ->assertOk()
        ->assertJsonPath('show.id', $secondShow->id);

    expect(DownloadPick::query()->orderBy('file_index')->pluck('episode_id', 'file_index')->all())
        ->toBe([0 => $firstShowEpisode->id, 1 => $secondShowEpisode->id])
        ->and(collect(qBittorrentRequestsTo('torrents/filePrio'))->last())->toBe(['hash' => DOWNLOAD_HASH, 'id' => '0|1', 'priority' => '1']);
});

test('a file another show picked can not be picked again', function () {
    [, $firstShowEpisode] = showWithTwoEpisodes();
    [$secondShow, $secondShowEpisode] = showWithTwoEpisodes();
    $download = Download::factory()->create(['hash' => DOWNLOAD_HASH, 'status' => DownloadStatus::Downloading]);
    DownloadPick::factory()->for($download)->create(['file_index' => 0, 'episode_id' => $firstShowEpisode->id]);
    fakeQBittorrentApi(['torrents/info' => qBittorrentAnswer('torrents-info'), 'torrents/files' => qBittorrentAnswer('torrents-files')]);
    actingAsAdmin();

    $response = putJson("/api/v1/downloads/{$download->id}/files", ['show_id' => $secondShow->id, 'files' => [['index' => 0, 'episode_id' => $secondShowEpisode->id]]])
        ->assertUnprocessable();

    expect($response->json('errors')['files.0.index'][0]['code'])->toBe('file_already_picked')
        ->and(DownloadPick::query()->sole()->episode_id)->toBe($firstShowEpisode->id);
});

test('a torrent added without a name shows qBittorrent\'s, and a long name is shortened', function () {
    fakeQBittorrentApi(['torrents/info' => qBittorrentAnswer('torrents-info'), 'torrents/files' => []]);
    actingAsAdmin();

    postJson('/api/v1/downloads', ['magnet_url' => DOWNLOAD_MAGNET])
        ->assertCreated()
        ->assertJsonPath('name', '[anonymous] Mushoku Tensei (2021) S01 (1080p BD Remux)');

    Download::query()->delete();

    postJson('/api/v1/downloads', ['magnet_url' => DOWNLOAD_MAGNET, 'name' => str_repeat('無職転生 ', 100)])->assertCreated();

    expect(mb_strlen(Download::query()->sole()->name))->toBe(255);
});

test('the list shows every download newest first, live from one qBittorrent request', function () {
    [$show, $made, $replaced] = showWithTwoEpisodes();
    $show->titles()->create(['title' => 'Mushoku Tensei', 'is_primary' => true]);
    $made->update(['filename' => '']);
    $replaced->update(['filename' => 'Old release.mkv']);
    $lost = Download::factory()->create(['hash' => str_repeat('a', 40), 'created_at' => now()->subDay()]);
    $download = Download::factory()->create(['hash' => DOWNLOAD_HASH, 'show_id' => $show->id, 'status' => DownloadStatus::Downloading]);
    DownloadPick::factory()->for($download)->create(['file_index' => 0, 'episode_id' => $made->id]);
    DownloadPick::factory()->for($download)->create(['file_index' => 1, 'episode_id' => $replaced->id]);
    fakeQBittorrentApi(['torrents/info' => qBittorrentAnswer('torrents-info')]);
    actingAsAdmin();

    getJson('/api/v1/downloads')
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.id', $download->id)
        ->assertJsonPath('0.status', 'downloading')
        ->assertJsonPath('0.progress', 0.25)
        ->assertJsonPath('0.download_speed', 5000000)
        ->assertJsonPath('0.show.titles.0.title', 'Mushoku Tensei')
        // Only the episode made for it: the other keeps its own video when cancelled
        ->assertJsonPath('0.waiting_episode_count', 1)
        ->assertJsonMissingPath('0.files')
        ->assertJsonPath('1.id', $lost->id)
        ->assertJsonPath('1.status', 'failed')
        ->assertJsonPath('1.error_code', 'client_lost_torrent');

    expect(qBittorrentRequestsTo('torrents/info'))->toBe([['hashes' => DOWNLOAD_HASH.'|'.str_repeat('a', 40)]]);
});

test('an empty list asks qBittorrent nothing', function () {
    actingAsAdmin();

    getJson('/api/v1/downloads')->assertOk()->assertExactJson([]);
    Saloon::assertNothingSent();
});

test('cancelling removes the torrent and its data, and keeps the episodes', function () {
    [$show, $episode] = showWithTwoEpisodes();
    $episode->update(['filename' => '']);
    $download = Download::factory()->create(['hash' => DOWNLOAD_HASH, 'show_id' => $show->id]);
    DownloadPick::factory()->for($download)->create(['file_index' => 0, 'episode_id' => $episode->id]);
    fakeQBittorrentApi();
    actingAsAdmin();

    deleteJson("/api/v1/downloads/{$download->id}")->assertNoContent();

    expect(Download::query()->count())->toBe(0)
        ->and(DownloadPick::query()->count())->toBe(0)
        ->and(Episode::query()->find($episode->id))->not->toBeNull()
        ->and(qBittorrentRequestsTo('torrents/delete'))->toBe([['hashes' => DOWNLOAD_HASH, 'deleteFiles' => 'true']]);
});

test('cancelling can remove the episodes made for it, but never one that has its own video', function () {
    [$show, $made, $replaced] = showWithTwoEpisodes();
    $made->update(['filename' => '']);
    $replaced->update(['filename' => 'Old release.mkv']);
    $download = Download::factory()->create(['hash' => DOWNLOAD_HASH, 'show_id' => $show->id]);
    DownloadPick::factory()->for($download)->create(['file_index' => 0, 'episode_id' => $made->id]);
    DownloadPick::factory()->for($download)->create(['file_index' => 1, 'episode_id' => $replaced->id]);
    fakeQBittorrentApi();
    actingAsAdmin();

    deleteJson("/api/v1/downloads/{$download->id}?remove_episodes=1")->assertNoContent();

    expect(Episode::query()->find($made->id))->toBeNull()
        ->and(Episode::query()->find($replaced->id))->not->toBeNull();
});

test('a download can not be cancelled while its files are copied into their episodes', function () {
    $download = Download::factory()->create(['hash' => DOWNLOAD_HASH, 'status' => DownloadStatus::Importing]);
    Saloon::fake([QBittorrentConnector::class => saloonConnectionRefused()]);
    actingAsAdmin();

    deleteJson("/api/v1/downloads/{$download->id}")
        ->assertStatus(409)
        ->assertJsonPath('code', 'download_import_running');

    expect(Download::query()->find($download->id))->not->toBeNull();
});

test('a closed qBittorrent keeps the download to cancel again', function () {
    $download = Download::factory()->create(['hash' => DOWNLOAD_HASH]);
    Saloon::fake([QBittorrentConnector::class => saloonConnectionRefused()]);
    actingAsAdmin();

    deleteJson("/api/v1/downloads/{$download->id}")->assertStatus(502);

    expect(Download::query()->find($download->id))->not->toBeNull();
});

test('listing and cancelling downloads require an admin', function () {
    $download = Download::factory()->create();

    getJson('/api/v1/downloads')->assertUnauthorized();
    deleteJson("/api/v1/downloads/{$download->id}")->assertUnauthorized();

    withToken(User::factory()->create()->createToken('test-token')->plainTextToken);

    getJson('/api/v1/downloads')->assertForbidden();
    deleteJson("/api/v1/downloads/{$download->id}")->assertForbidden();
    Saloon::assertNothingSent();
});

test('a download counts the episodes made for it, for cancelling', function () {
    [$show, $made, $replaced] = showWithTwoEpisodes();
    $made->update(['filename' => '']);
    $replaced->update(['filename' => 'Old release.mkv']);
    $download = Download::factory()->create(['hash' => DOWNLOAD_HASH, 'show_id' => $show->id, 'status' => DownloadStatus::Downloading]);
    DownloadPick::factory()->for($download)->create(['file_index' => 0, 'episode_id' => $made->id]);
    DownloadPick::factory()->for($download)->create(['file_index' => 1, 'episode_id' => $replaced->id]);
    fakeQBittorrentApi(['torrents/info' => qBittorrentAnswer('torrents-info'), 'torrents/files' => qBittorrentAnswer('torrents-files')]);
    actingAsAdmin();

    getJson("/api/v1/downloads/{$download->id}")->assertOk()->assertJsonPath('waiting_episode_count', 1);
});
