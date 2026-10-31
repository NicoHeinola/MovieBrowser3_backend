<?php

use App\Actions\EpisodePlayback\OpenVlcPlaylistAction;
use App\Exceptions\VlcLaunchException;
use App\Models\Setting\Setting;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyProcessTimedOutException;
use Symfony\Component\Process\Process as SymfonyProcess;

function useVlcBinary(string $name): string
{
    $binary = putVideoFile(videoBasePathForTests()."/bin/{$name}");
    Setting::query()->whereKey('vlc_media_player_path')->update(['value' => $binary]);

    return $binary;
}

/**
 * Stands in for a Linux VLC: listens on the RC port the action picked.
 */
function fakeLinuxVlc(mixed &$remoteControl): void
{
    Process::fake(function (PendingProcess $process) use (&$remoteControl) {
        $host = $process->command[array_search('--rc-host', $process->command, true) + 1];
        $remoteControl = stream_socket_server("tcp://{$host}");

        return Process::result();
    });
}

test('it launches VLC detached and builds the playlist over the RC interface', function () {
    $binary = useVlcBinary('vlc');
    fakeLinuxVlc($remoteControl);

    app(OpenVlcPlaylistAction::class)->handle(['/videos/ep 1.mkv', '/videos/ep 2.mkv', '/videos/ep 3.mkv'], 1);

    Process::assertRan(fn (PendingProcess $process): bool => array_slice($process->command, 0, 7) === [
        'sh', '-c', 'nohup "$@" >/dev/null 2>&1 &', 'sh', $binary, '--extraintf', 'rc',
    ] && !in_array('--rc-quiet', $process->command, true));

    $connection = stream_socket_accept($remoteControl, 1);

    expect(stream_get_contents($connection))->toBe(
        "enqueue /videos/ep 1.mkv\nadd /videos/ep 2.mkv\nenqueue /videos/ep 3.mkv\n"
    );
});

test('it falls back to vlc on the PATH when no binary is configured', function () {
    fakeLinuxVlc($remoteControl);

    app(OpenVlcPlaylistAction::class)->handle(['/videos/ep 1.mkv'], 0);

    Process::assertRan(fn (PendingProcess $process): bool => $process->command[4] === 'vlc');
});

test('a Windows VLC gets Windows paths and its playlist through PowerShell', function () {
    $binary = useVlcBinary('vlc.exe');
    $sent = null;

    Process::fake(function (PendingProcess $process) use (&$sent) {
        if ($process->command[0] === 'wslpath') {
            return Process::result(output: 'G:\\videos\\'.basename($process->command[2])."\n");
        }

        if (str_ends_with($process->command[0], 'powershell.exe')) {
            preg_match("/FromBase64String\\('([^']+)'\\)/", $process->command[4], $matches);
            $sent = base64_decode($matches[1]);
        }

        return Process::result();
    });

    app(OpenVlcPlaylistAction::class)->handle(['/mnt/g/videos/ep 1.mkv', '/mnt/g/videos/ep 2.mkv'], 1);

    Process::assertRan(fn (PendingProcess $process): bool => ($process->command[4] ?? null) === $binary
        && in_array('--rc-quiet', $process->command, true));

    expect($sent)->toBe("enqueue G:\\videos\\ep 1.mkv\nadd G:\\videos\\ep 2.mkv\n");
});

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

test('it fails when VLC on Windows never opens its RC port', function () {
    useVlcBinary('vlc.exe');
    Process::fake([
        'wslpath *' => Process::result(output: "G:\\videos\\ep 1.mkv\n"),
        '*powershell.exe*' => Process::result(exitCode: 1),
        '*' => Process::result(),
    ]);

    app(OpenVlcPlaylistAction::class)->handle(['/mnt/g/videos/ep 1.mkv'], 0);
})->throws(VlcLaunchException::class, 'VLC did not start.');

test('it fails when PowerShell times out waiting for VLC on Windows', function () {
    useVlcBinary('vlc.exe');
    Process::fake(function (PendingProcess $process) {
        if (str_ends_with($process->command[0], 'powershell.exe')) {
            throw new ProcessTimedOutException(
                new SymfonyProcessTimedOutException(new SymfonyProcess(['powershell.exe']), SymfonyProcessTimedOutException::TYPE_GENERAL),
                Process::result(exitCode: 1),
            );
        }

        return $process->command[0] === 'wslpath' ? Process::result(output: "G:\\videos\\ep 1.mkv\n") : Process::result();
    });

    app(OpenVlcPlaylistAction::class)->handle(['/mnt/g/videos/ep 1.mkv'], 0);
})->throws(VlcLaunchException::class, 'VLC did not start.');
