<?php

use App\Actions\EpisodePlayback\OpenVlcPlaylistAction;
use App\Actions\EpisodePlayback\ResolveEphemeralPortRangeAction;
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

    // Below Windows' own dynamic range (49152 and up)
    expect($vlc->port)->toBeGreaterThanOrEqual(40000)->toBeLessThanOrEqual(49151);

    expect(sentVlcCommands())->toBe([
        'in_enqueue /videos/ep 1.mkv',
        'in_play /videos/ep 2.mkv',
        'in_enqueue /videos/ep 3.mkv',
    ]);

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), "http://127.0.0.1:{$vlc->port}/requests/status.json")
        && $request->hasHeader('Authorization', 'Basic '.base64_encode(":{$vlc->password}")));
});

test('the port skips Linux\'s ephemeral range, where a Windows vlc.exe cannot listen under WSL mirrored networking', function () {
    useVlcBinary('vlc');
    Process::fake();
    Http::fake(['127.0.0.1:*' => Http::response(['state' => 'stopped'])]);
    // Leaves only 49151 free: everything else in the range is Linux's
    ResolveEphemeralPortRangeAction::mock()->shouldReceive('handle')->andReturn([40000, 49150]);

    $vlc = app(OpenVlcPlaylistAction::class)->handle(['/videos/ep 1.mkv'], 0);

    expect($vlc->port)->toBe(49151);
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

describe('with the bridge launcher', function () {
    beforeEach(function () {
        config(['vlc.launcher' => 'bridge', 'vlc.bridge_url' => 'http://127.0.0.1:8767', 'vlc.bridge_token' => 'bridge-token']);
        Process::fake();
    });

    test('the bridge starts VLC and converts the paths for a Windows VLC', function () {
        // The container cannot see the binary, so it is not checked here
        Setting::query()->whereKey('vlc_media_player_path')->update(['value' => '/mnt/c/Program Files/VideoLAN/VLC/vlc.exe']);
        Http::fake([
            '127.0.0.1:8767/windows-paths' => Http::response(['paths' => ['G:\\videos\\ep 1.mkv', 'G:\\videos\\ep 2.mkv']]),
            '127.0.0.1:8767/launch' => Http::response(status: 204),
            '127.0.0.1:*' => Http::response(['state' => 'stopped']),
        ]);

        $vlc = app(OpenVlcPlaylistAction::class)->handle(['/mnt/g/videos/ep 1.mkv', '/mnt/g/videos/ep 2.mkv'], 1);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://127.0.0.1:8767/windows-paths'
            && $request->hasHeader('Authorization', 'Bearer bridge-token')
            && $request->data() === ['paths' => ['/mnt/g/videos/ep 1.mkv', '/mnt/g/videos/ep 2.mkv']]);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://127.0.0.1:8767/launch'
            && $request->hasHeader('Authorization', 'Bearer bridge-token')
            && $request->data() === [
                'binary' => '/mnt/c/Program Files/VideoLAN/VLC/vlc.exe',
                'port' => $vlc->port,
                'password' => $vlc->password,
            ]);
        expect(sentVlcCommands())->toBe([
            'in_enqueue G:\\videos\\ep 1.mkv',
            'in_play G:\\videos\\ep 2.mkv',
        ]);
        Process::assertNothingRan();
    });

    test('a Linux VLC keeps its paths', function () {
        Http::fake([
            '127.0.0.1:8767/launch' => Http::response(status: 204),
            '127.0.0.1:*' => Http::response(['state' => 'stopped']),
        ]);

        app(OpenVlcPlaylistAction::class)->handle(['/videos/ep 1.mkv'], 0);

        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/windows-paths'));
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/launch') && $request['binary'] === 'vlc');
        expect(sentVlcCommands())->toBe(['in_play /videos/ep 1.mkv']);
    });

    test('it passes on why the bridge rejected the launch', function () {
        Http::fake(['127.0.0.1:8767/launch' => Http::response(['message' => 'VLC was not found at /missing/vlc.'], 422)]);

        app(OpenVlcPlaylistAction::class)->handle(['/videos/ep 1.mkv'], 0);
    })->throws(VlcLaunchException::class, 'VLC was not found at /missing/vlc.');

    test('it fails when the bridge rejects the token', function () {
        Http::fake(['127.0.0.1:8767/launch' => Http::response(status: 403)]);

        app(OpenVlcPlaylistAction::class)->handle(['/videos/ep 1.mkv'], 0);
    })->throws(VlcLaunchException::class, 'The VLC bridge answered 403. Check VLC_BRIDGE_TOKEN.');

    test('it fails when the bridge is not running', function () {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        app(OpenVlcPlaylistAction::class)->handle(['/videos/ep 1.mkv'], 0);
    })->throws(VlcLaunchException::class, 'The VLC bridge at http://127.0.0.1:8767 did not answer.');
});

test('the ephemeral range is read from Linux', function () {
    $range = ResolveEphemeralPortRangeAction::run();

    // Linux's default is 32768-60999; WSL mirrored networking narrows it to 4096 ports
    expect($range)->toBeArray()->toHaveCount(2)
        ->and($range[0])->toBeLessThan($range[1]);
});
