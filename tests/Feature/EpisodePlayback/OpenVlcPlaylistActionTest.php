<?php

use App\Actions\EpisodePlayback\OpenVlcPlaylistAction;
use App\Actions\EpisodePlayback\ResolveEphemeralPortRangeAction;
use App\Exceptions\HostBridgeException;
use App\Exceptions\VlcLaunchException;
use App\Models\Setting\Setting;
use App\Services\HostBridge\HostBridgeConnector;
use App\Services\HostBridge\Requests\ConvertToWindowsPathsRequest;
use App\Services\HostBridge\Requests\LaunchRequest;
use App\Services\Vlc\Requests\GetStatusRequest;
use App\Services\Vlc\VlcConnector;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

beforeEach(fn () => Sleep::fake());

function useVlcBinary(string $name): string
{
    $binary = putVideoFile(videoBaseFolderForTests()."/bin/{$name}");
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
    return saloonSent()
        ->map(fn (array $sent): array => $sent['request']->query()->all())
        ->filter(fn (array $query): bool => isset($query['command']))
        ->map(fn (array $query): string => "{$query['command']} {$query['input']}")
        ->values()
        ->all();
}

test('it starts VLC with its web interface and builds the playlist through it', function () {
    $binary = useVlcBinary('vlc');
    Process::fake();
    Saloon::fake(['127.0.0.1:*' => MockResponse::make(['state' => 'stopped'])]);

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

    $status = saloonSent(GetStatusRequest::class)->first()['request'];
    expect($status->getUrl())->toBe("http://127.0.0.1:{$vlc->port}/requests/status.json")
        ->and($status->headers()->get('Authorization'))->toBe('Basic '.base64_encode(":{$vlc->password}"));
});

test('a start time only applies to the episode that plays first', function () {
    useVlcBinary('vlc');
    Process::fake();
    Saloon::fake(['127.0.0.1:*' => MockResponse::make(['state' => 'stopped'])]);

    app(OpenVlcPlaylistAction::class)->handle(['/videos/ep 1.mkv', '/videos/ep 2.mkv', '/videos/ep 3.mkv'], 1, 754);

    $options = saloonSent()
        ->map(fn (array $sent): array => $sent['request']->query()->all())
        ->filter(fn (array $query): bool => isset($query['command']))
        ->mapWithKeys(fn (array $query): array => [$query['input'] => $query['option'] ?? null])
        ->all();

    expect($options)->toBe([
        '/videos/ep 1.mkv' => null,
        '/videos/ep 2.mkv' => 'start-time=754',
        '/videos/ep 3.mkv' => null,
    ]);
});

test('the port skips Linux\'s ephemeral range, where a Windows vlc.exe cannot listen under WSL mirrored networking', function () {
    useVlcBinary('vlc');
    Process::fake();
    Saloon::fake(['127.0.0.1:*' => MockResponse::make(['state' => 'stopped'])]);
    // Leaves only 49151 free: everything else in the range is Linux's
    ResolveEphemeralPortRangeAction::mock()->shouldReceive('handle')->andReturn([40000, 49150]);

    $vlc = app(OpenVlcPlaylistAction::class)->handle(['/videos/ep 1.mkv'], 0);

    expect($vlc->port)->toBe(49151);
});

test('it falls back to vlc on the PATH when no binary is configured', function () {
    Process::fake();
    Saloon::fake(['127.0.0.1:*' => MockResponse::make(['state' => 'stopped'])]);

    app(OpenVlcPlaylistAction::class)->handle(['/videos/ep 1.mkv'], 0);

    Process::assertRan(fn (PendingProcess $process): bool => $process->command[4] === 'vlc');
});

test('a Windows VLC gets the Windows form of every path', function () {
    useVlcBinary('vlc.exe');
    Process::fake(fn (PendingProcess $process) => $process->command[0] === 'wslpath'
        ? Process::result(output: 'G:\\videos\\'.basename($process->command[2])."\n")
        : Process::result());
    Saloon::fake(['127.0.0.1:*' => MockResponse::make(['state' => 'stopped'])]);

    app(OpenVlcPlaylistAction::class)->handle(['/mnt/g/videos/ep 1.mkv', '/mnt/g/videos/ep 2.mkv'], 1);

    expect(sentVlcCommands())->toBe([
        'in_enqueue G:\\videos\\ep 1.mkv',
        'in_play G:\\videos\\ep 2.mkv',
    ]);
});

test('it waits for VLC to start answering', function () {
    Process::fake();
    Saloon::fake([VlcConnector::class => saloonSequence(
        [saloonConnectionRefused(), saloonConnectionRefused()],
        whenEmpty: MockResponse::make(['state' => 'stopped']),
    )]);

    app(OpenVlcPlaylistAction::class)->handle(['/videos/ep 1.mkv'], 0);

    Sleep::assertSleptTimes(2);
    expect(sentVlcCommands())->toBe(['in_play /videos/ep 1.mkv']);
});

test('it fails when VLC never starts answering', function () {
    Process::fake();
    Saloon::fake([VlcConnector::class => saloonConnectionRefused()]);

    app(OpenVlcPlaylistAction::class)->handle(['/videos/ep 1.mkv'], 0);
})->throws(VlcLaunchException::class, 'VLC did not start.');

test('it fails when VLC is closed while the playlist is built', function () {
    Process::fake();
    Saloon::fake([VlcConnector::class => saloonSequence([MockResponse::make(['state' => 'stopped']), saloonConnectionRefused()])]);

    app(OpenVlcPlaylistAction::class)->handle(['/videos/ep 1.mkv'], 0);
})->throws(VlcLaunchException::class, 'VLC was closed before the playlist was built.');

test('it fails when the launch command fails', function () {
    Process::fake(fn () => Process::result(errorOutput: 'sh: not found', exitCode: 127));

    app(OpenVlcPlaylistAction::class)->handle(['/videos/ep 1.mkv'], 0);
})->throws(VlcLaunchException::class, 'VLC could not be started: sh: not found');

describe('with the host bridge', function () {
    beforeEach(function () {
        config(['host_bridge.enabled' => true, 'host_bridge.url' => 'http://127.0.0.1:8767', 'host_bridge.token' => 'bridge-token']);
        Process::fake();
    });

    test('the bridge starts VLC and converts the paths for a Windows VLC', function () {
        // The container cannot see the binary, so it is not checked here
        Setting::query()->whereKey('vlc_media_player_path')->update(['value' => '/mnt/c/Program Files/VideoLAN/VLC/vlc.exe']);
        Saloon::fake([
            '127.0.0.1:8767/windows-paths' => MockResponse::make(['paths' => ['G:\\videos\\ep 1.mkv', 'G:\\videos\\ep 2.mkv']]),
            '127.0.0.1:8767/launch' => MockResponse::make(status: 204),
            '127.0.0.1:*' => MockResponse::make(['state' => 'stopped']),
        ]);

        $vlc = app(OpenVlcPlaylistAction::class)->handle(['/mnt/g/videos/ep 1.mkv', '/mnt/g/videos/ep 2.mkv'], 1);

        $paths = saloonSent(ConvertToWindowsPathsRequest::class)->sole();
        expect($paths['request']->getUrl())->toBe('http://127.0.0.1:8767/windows-paths')
            ->and($paths['request']->headers()->get('Authorization'))->toBe('Bearer bridge-token')
            ->and($paths['fields'])->toBe(['paths' => ['/mnt/g/videos/ep 1.mkv', '/mnt/g/videos/ep 2.mkv']]);
        $launch = saloonSent(LaunchRequest::class)->sole();
        expect($launch['request']->getUrl())->toBe('http://127.0.0.1:8767/launch')
            ->and($launch['request']->headers()->get('Authorization'))->toBe('Bearer bridge-token')
            ->and($launch['fields'])->toBe([
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
        Saloon::fake([
            '127.0.0.1:8767/launch' => MockResponse::make(status: 204),
            '127.0.0.1:*' => MockResponse::make(['state' => 'stopped']),
        ]);

        app(OpenVlcPlaylistAction::class)->handle(['/videos/ep 1.mkv'], 0);

        expect(saloonSent(ConvertToWindowsPathsRequest::class))->toBeEmpty()
            ->and(saloonSent(LaunchRequest::class)->sole()['fields']['binary'])->toBe('vlc');
        expect(sentVlcCommands())->toBe(['in_play /videos/ep 1.mkv']);
    });

    test('it passes on why the bridge rejected the launch', function () {
        Saloon::fake(['127.0.0.1:8767/launch' => MockResponse::make(['message' => 'VLC was not found at /missing/vlc.'], 422)]);

        app(OpenVlcPlaylistAction::class)->handle(['/videos/ep 1.mkv'], 0);
    })->throws(HostBridgeException::class, 'VLC was not found at /missing/vlc.');

    test('it fails when the bridge rejects the token', function () {
        Saloon::fake(['127.0.0.1:8767/launch' => MockResponse::make(status: 403)]);

        app(OpenVlcPlaylistAction::class)->handle(['/videos/ep 1.mkv'], 0);
    })->throws(HostBridgeException::class, 'The host bridge answered 403. Check HOST_BRIDGE_TOKEN.');

    test('it fails when the bridge is not running', function () {
        Saloon::fake([HostBridgeConnector::class => saloonConnectionRefused()]);

        app(OpenVlcPlaylistAction::class)->handle(['/videos/ep 1.mkv'], 0);
    })->throws(HostBridgeException::class, 'The host bridge at http://127.0.0.1:8767 did not answer.');
});

test('the ephemeral range is read from Linux', function () {
    $range = ResolveEphemeralPortRangeAction::run();

    // Linux's default is 32768-60999; WSL mirrored networking narrows it to 4096 ports
    expect($range)->toBeArray()->toHaveCount(2)
        ->and($range[0])->toBeLessThan($range[1]);
});
