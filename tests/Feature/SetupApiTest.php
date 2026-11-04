<?php

use App\Models\Setting\Setting;
use App\Models\User\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withToken;

/**
 * Fakes the VLC bridge so only $existing passes its check-binary check.
 */
function fakeBridgeWithVlcAt(?string $existing): void
{
    config(['vlc.launcher' => 'bridge', 'vlc.bridge_url' => 'http://127.0.0.1:8767', 'vlc.bridge_token' => 'bridge-token']);
    Http::fake(fn (Request $request) => $request['binary'] === $existing
        ? Http::response(status: 204)
        : Http::response(['message' => "VLC was not found at {$request['binary']}.", 'code' => 'vlc_not_found'], 422));
}

test('setup requires an admin', function () {
    getJson('/api/v1/setup')->assertUnauthorized();
    postJson('/api/v1/setup/checks', ['video_base_path' => '/videos'])->assertUnauthorized();
    postJson('/api/v1/setup/complete')->assertUnauthorized();

    withToken(User::factory()->create()->createToken('test-token')->plainTextToken);

    getJson('/api/v1/setup')->assertForbidden();
    postJson('/api/v1/setup/checks', ['video_base_path' => '/videos'])->assertForbidden();
    postJson('/api/v1/setup/complete')->assertForbidden();
});

test('a new install is not set up and lists the mounted media folders', function () {
    config(['deploy.media_folders' => ['/mnt/g/New_Vid', '/mnt/h/Anime']]);
    fakeBridgeWithVlcAt(null);
    actingAsAdmin();

    getJson('/api/v1/setup')
        ->assertOk()
        ->assertExactJson([
            'completed_at' => null,
            'media_folders' => ['/mnt/g/New_Vid', '/mnt/h/Anime'],
            'detected_vlc_media_player_path' => null,
        ]);
});

test('it suggests the first usual VLC location the bridge finds', function () {
    fakeBridgeWithVlcAt('/mnt/c/Program Files (x86)/VideoLAN/VLC/vlc.exe');
    actingAsAdmin();

    getJson('/api/v1/setup')
        ->assertOk()
        ->assertJsonPath('detected_vlc_media_player_path', '/mnt/c/Program Files (x86)/VideoLAN/VLC/vlc.exe');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'http://127.0.0.1:8767/check-binary'
        && $request->hasHeader('Authorization', 'Bearer bridge-token'));
});

test('without the bridge it finds vlc on the PATH', function () {
    config(['vlc.detect_paths' => [videoBasePathForTests().'/missing/vlc', 'vlc']]);
    Process::fake(fn (PendingProcess $process) => Process::result(exitCode: $process->command[4] === 'vlc' ? 0 : 1));
    actingAsAdmin();

    getJson('/api/v1/setup')->assertOk()->assertJsonPath('detected_vlc_media_player_path', 'vlc');
});

test('it checks only the values that were sent and saves nothing', function () {
    fakeBridgeWithVlcAt('/mnt/c/vlc/vlc.exe');
    actingAsAdmin();

    postJson('/api/v1/setup/checks', ['vlc_media_player_path' => '/mnt/c/vlc/vlc.exe'])
        ->assertOk()
        ->assertExactJson(['vlc_media_player_path' => ['ok' => true, 'code' => null, 'params' => []]]);

    postJson('/api/v1/setup/checks', ['vlc_media_player_path' => '/mnt/c/missing/vlc.exe'])
        ->assertOk()
        ->assertExactJson(['vlc_media_player_path' => ['ok' => false, 'code' => 'vlc_not_found', 'params' => ['path' => '/mnt/c/missing/vlc.exe']]]);

    expect(Setting::query()->whereKey('vlc_media_player_path')->value('value'))->toBe('');
});

test('an empty VLC path is checked as vlc on the PATH', function () {
    fakeBridgeWithVlcAt('vlc');
    actingAsAdmin();

    postJson('/api/v1/setup/checks', ['vlc_media_player_path' => ''])
        ->assertOk()
        ->assertJsonPath('vlc_media_player_path.ok', true);
});

test('it says when the bridge is too old to check VLC', function () {
    config(['vlc.launcher' => 'bridge', 'vlc.bridge_url' => 'http://127.0.0.1:8767']);
    Http::fake(['127.0.0.1:8767/check-binary' => Http::response(['message' => 'Not found.'], 404)]);
    actingAsAdmin();

    postJson('/api/v1/setup/checks', ['vlc_media_player_path' => '/mnt/c/vlc/vlc.exe'])
        ->assertJsonPath('vlc_media_player_path.code', 'vlc_bridge_outdated');
});

test('it explains why the bridge cannot check VLC', function () {
    config(['vlc.launcher' => 'bridge', 'vlc.bridge_url' => 'http://127.0.0.1:8767']);
    Http::fake(fn () => throw new ConnectionException('Connection refused'));
    actingAsAdmin();

    postJson('/api/v1/setup/checks', ['vlc_media_player_path' => '/mnt/c/vlc/vlc.exe'])
        ->assertOk()
        ->assertJsonPath('vlc_media_player_path.ok', false)
        ->assertJsonPath('vlc_media_player_path.code', 'vlc_bridge_unreachable')
        ->assertJsonPath('vlc_media_player_path.params.url', 'http://127.0.0.1:8767');
});

test('it sends the code for each way the bridge turns a VLC check down', function (int $status, array $body, string $code, array $params) {
    config(['vlc.launcher' => 'bridge', 'vlc.bridge_url' => 'http://127.0.0.1:8767']);
    Http::fake(['127.0.0.1:8767/check-binary' => Http::response($body, $status)]);
    actingAsAdmin();

    postJson('/api/v1/setup/checks', ['vlc_media_player_path' => '/mnt/c/vlc/other.exe'])
        ->assertOk()
        ->assertExactJson(['vlc_media_player_path' => ['ok' => false, 'code' => $code, 'params' => $params]]);
})->with([
    'wrong binary' => [422, ['message' => 'Not VLC.', 'code' => 'vlc_wrong_binary'], 'vlc_wrong_binary', ['path' => '/mnt/c/vlc/other.exe']],
    'older bridge without a code' => [422, ['message' => 'Not VLC.'], 'vlc_bridge_rejected', []],
    'unknown code' => [422, ['message' => 'New.', 'code' => 'vlc_something_new'], 'vlc_bridge_rejected', []],
    'bad token' => [403, ['message' => 'Forbidden.'], 'vlc_bridge_unauthorized', ['status' => '403']],
]);

test('checks encode empty params as a JSON object', function () {
    actingAsAdmin();

    $body = postJson('/api/v1/setup/checks', ['video_base_path' => videoBasePathForTests()])->getContent();

    expect($body)->toContain('"params":{}');
});

test('without the bridge only a VLC that exists passes', function () {
    $vlc = putVideoFile(videoBasePathForTests().'/bin/vlc');
    $other = putVideoFile(videoBasePathForTests().'/bin/notvlc');
    actingAsAdmin();

    postJson('/api/v1/setup/checks', ['vlc_media_player_path' => $vlc])->assertJsonPath('vlc_media_player_path.ok', true);
    postJson('/api/v1/setup/checks', ['vlc_media_player_path' => videoBasePathForTests().'/bin/missing/vlc'])
        ->assertJsonPath('vlc_media_player_path.code', 'vlc_not_found')
        ->assertJsonPath('vlc_media_player_path.params.path', videoBasePathForTests().'/bin/missing/vlc');
    postJson('/api/v1/setup/checks', ['vlc_media_player_path' => $other])
        ->assertJsonPath('vlc_media_player_path.code', 'vlc_wrong_binary')
        ->assertJsonPath('vlc_media_player_path.params.path', $other);
});

test('a video folder passes when it is writable or can be created', function () {
    $base = videoBasePathForTests();
    actingAsAdmin();

    postJson('/api/v1/setup/checks', ['video_base_path' => $base])
        ->assertOk()
        ->assertExactJson(['video_base_path' => ['ok' => true, 'code' => null, 'params' => []]]);
    postJson('/api/v1/setup/checks', ['video_base_path' => "{$base}/new/MovieBrowser3"])->assertJsonPath('video_base_path.ok', true);
});

test('it says what is wrong with a video folder', function (string $path, string $code) {
    putVideoFile(videoBasePathForTests().'/a-file.mkv');
    actingAsAdmin();

    postJson('/api/v1/setup/checks', ['video_base_path' => str_replace('{base}', videoBasePathForTests(), $path)])
        ->assertOk()
        ->assertJsonPath('video_base_path.ok', false)
        ->assertJsonPath('video_base_path.code', $code)
        ->assertJsonPath('video_base_path.params', []);
})->with([
    'empty' => ['', 'path_required'],
    'relative' => ['videos', 'path_not_absolute'],
    'windows' => ['G:\\New_Vid_Dev', 'path_not_absolute'],
    'dot dot' => ['{base}/../elsewhere', 'path_has_parent_segment'],
    'a file' => ['{base}/a-file.mkv', 'path_is_file'],
]);

test('a video folder that cannot be written to fails', function () {
    $locked = videoBasePathForTests().'/locked';
    mkdir($locked, recursive: true);
    chmod($locked, 0555);
    actingAsAdmin();

    try {
        postJson('/api/v1/setup/checks', ['video_base_path' => $locked])
            ->assertJsonPath('video_base_path.code', 'path_not_writable');
        postJson('/api/v1/setup/checks', ['video_base_path' => "{$locked}/inside"])
            ->assertJsonPath('video_base_path.code', 'path_not_creatable')
            ->assertJsonPath('video_base_path.params.parent', $locked);
    } finally {
        chmod($locked, 0755);
    }
})->skip(fn (): bool => posix_geteuid() === 0, 'root ignores file permissions');

test('in prod a video folder must be inside a mounted media folder', function () {
    $base = videoBasePathForTests();
    config(['deploy.media_folders' => [$base, '/mnt/h/Vids']]);
    actingAsAdmin();

    postJson('/api/v1/setup/checks', ['video_base_path' => "{$base}/MovieBrowser3"])->assertJsonPath('video_base_path.ok', true);
    postJson('/api/v1/setup/checks', ['video_base_path' => "{$base}-other"])
        ->assertJsonPath('video_base_path.ok', false)
        ->assertJsonPath('video_base_path.code', 'path_outside_media_folders')
        ->assertJsonPath('video_base_path.params.folders', "{$base}, /mnt/h/Vids");
});

test('a check needs at least one string value', function () {
    actingAsAdmin();

    postJson('/api/v1/setup/checks', [])->assertUnprocessable()->assertJsonValidationErrors(['video_base_path', 'vlc_media_player_path']);
    postJson('/api/v1/setup/checks', ['video_base_path' => ['/videos']])->assertUnprocessable()->assertJsonValidationErrors(['video_base_path']);
});

test('completing setup keeps the first time', function () {
    fakeBridgeWithVlcAt(null);
    Carbon::setTestNow('2026-11-01 12:00:00');
    actingAsAdmin();

    postJson('/api/v1/setup/complete')
        ->assertCreated()
        ->assertJsonPath('completed_at', '2026-11-01T12:00:00.000000Z')
        ->assertJsonPath('detected_vlc_media_player_path', null);

    Carbon::setTestNow('2026-11-02 08:00:00');

    postJson('/api/v1/setup/complete')->assertCreated()->assertJsonPath('completed_at', '2026-11-01T12:00:00.000000Z');
    getJson('/api/v1/setup')->assertJsonPath('completed_at', '2026-11-01T12:00:00.000000Z');
});

test('setup_completed_at can only be set to a date or cleared', function () {
    fakeBridgeWithVlcAt(null);
    actingAsAdmin();

    patchJson('/api/v1/settings/setup_completed_at', ['value' => 'soon'])->assertUnprocessable()->assertJsonValidationErrors(['value']);
    patchJson('/api/v1/settings/setup_completed_at', ['value' => '2026-11-01T12:00:00.000000Z'])->assertOk();
    getJson('/api/v1/setup')->assertJsonPath('completed_at', '2026-11-01T12:00:00.000000Z');

    patchJson('/api/v1/settings/setup_completed_at', ['value' => ''])->assertOk();
    getJson('/api/v1/setup')->assertOk()->assertJsonPath('completed_at', null);
});
