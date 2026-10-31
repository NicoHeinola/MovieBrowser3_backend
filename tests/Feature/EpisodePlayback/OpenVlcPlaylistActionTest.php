<?php

use App\Actions\EpisodePlayback\OpenVlcPlaylistAction;
use App\Exceptions\VlcLaunchException;
use App\Models\Setting\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;

beforeEach(fn () => Sleep::fake());

function useVlcBinary(string $name): string
{
    $binary = putVideoFile(videoBasePathForTests()."/bin/{$name}");
    Setting::query()->whereKey('vlc_media_player_path')->update(['value' => $binary]);

    return $binary;
}

/**
 * The playlist commands VLC received, in order, as "command input" strings.
 *
 * @return list<string>
 */
function sentVlcCommands(): array
{
    return Http::recorded()
        ->map(fn (array $pair): array => $pair[0]->data())
        ->filter(fn (array $query): bool => isset($query['command']))
        ->map(fn (array $query): string => "{$query['command']} {$query['input']}")
        ->values()
        ->all();
}

test('it starts VLC with its web interface and builds the playlist through it', function () {
    $binary = useVlcBinary('vlc');
    Process::fake();
    Http::fake(['127.0.0.1:*' => Http::response(['state' => 'stopped'])]);

    $vlc = app(OpenVlcPlaylistAction::class)->handle(['/videos/ep 1.mkv', '/videos/ep 2.mkv', '/videos/ep 3.mkv'], 1);

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [
        'sh', '-c', 'nohup "$@" </dev/null >/dev/null 2>&1 &', 'sh',
        $binary, '--extraintf', 'http', '--http-host', '127.0.0.1',
        '--http-port', (string) $vlc->port, '--http-password', $vlc->password,
    ]);

    // A Windows vlc.exe cannot listen on Linux's ephemeral ports under WSL mirrored networking.
    expect($vlc->port)->toBeGreaterThanOrEqual(47000)->toBeLessThanOrEqual(47999);

    expect(sentVlcCommands())->toBe([
        'in_enqueue /videos/ep 1.mkv',
        'in_play /videos/ep 2.mkv',
        'in_enqueue /videos/ep 3.mkv',
    ]);

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), "http://127.0.0.1:{$vlc->port}/requests/status.json")
        && $request->hasHeader('Authorization', 'Basic '.base64_encode(":{$vlc->password}")));
});

test('it falls back to vlc on the PATH when no binary is configured', function () {
    Process::fake();
    Http::fake(['127.0.0.1:*' => Http::response(['state' => 'stopped'])]);

    app(OpenVlcPlaylistAction::class)->handle(['/videos/ep 1.mkv'], 0);

    Process::assertRan(fn (PendingProcess $process): bool => $process->command[4] === 'vlc');
});

test('a Windows VLC gets the Windows form of every path', function () {
    useVlcBinary('vlc.exe');
    Process::fake(fn (PendingProcess $process) => $process->command[0] === 'wslpath'
        ? Process::result(output: 'G:\\videos\\'.basename($process->command[2])."\n")
        : Process::result());
    Http::fake(['127.0.0.1:*' => Http::response(['state' => 'stopped'])]);

    app(OpenVlcPlaylistAction::class)->handle(['/mnt/g/videos/ep 1.mkv', '/mnt/g/videos/ep 2.mkv'], 1);

    expect(sentVlcCommands())->toBe([
        'in_enqueue G:\\videos\\ep 1.mkv',
        'in_play G:\\videos\\ep 2.mkv',
    ]);
});

test('it waits for VLC to start answering', function () {
    Process::fake();
    Http::fake(['127.0.0.1:*' => Http::sequence()
        ->pushFailedConnection()
        ->pushFailedConnection()
        ->whenEmpty(Http::response(['state' => 'stopped']))]);

    app(OpenVlcPlaylistAction::class)->handle(['/videos/ep 1.mkv'], 0);

    Sleep::assertSleptTimes(2);
    expect(sentVlcCommands())->toBe(['in_play /videos/ep 1.mkv']);
});

test('it fails when VLC never starts answering', function () {
    Process::fake();
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    app(OpenVlcPlaylistAction::class)->handle(['/videos/ep 1.mkv'], 0);
})->throws(VlcLaunchException::class, 'VLC did not start.');

test('it fails when VLC is closed while the playlist is built', function () {
    Process::fake();
    Http::fake(['127.0.0.1:*' => Http::sequence()
        ->push(['state' => 'stopped'])
        ->pushFailedConnection()]);

    app(OpenVlcPlaylistAction::class)->handle(['/videos/ep 1.mkv'], 0);
})->throws(VlcLaunchException::class, 'VLC was closed before the playlist was built.');

test('it fails when the configured binary does not exist', function () {
    Process::fake();
    Setting::query()->whereKey('vlc_media_player_path')->update(['value' => '/missing/vlc.exe']);

    try {
        app(OpenVlcPlaylistAction::class)->handle(['/videos/ep 1.mkv'], 0);
    } finally {
        Process::assertNothingRan();
    }
})->throws(VlcLaunchException::class, 'VLC was not found at /missing/vlc.exe.');

test('it fails when the launch command fails', function () {
    Process::fake(fn () => Process::result(errorOutput: 'sh: not found', exitCode: 127));

    app(OpenVlcPlaylistAction::class)->handle(['/videos/ep 1.mkv'], 0);
})->throws(VlcLaunchException::class, 'VLC could not be started: sh: not found');
