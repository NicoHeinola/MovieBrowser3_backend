<?php

namespace App\Actions\EpisodePlayback;

use App\Exceptions\VlcLaunchException;
use App\Models\Setting\Setting;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use Lorisleiva\Actions\Concerns\AsAction;

class OpenVlcPlaylistAction
{
    use AsAction;

    private const int CONNECT_TIMEOUT_MS = 10000;

    private const int CONNECT_RETRY_MS = 100;

    private const string POWERSHELL = '/mnt/c/Windows/System32/WindowsPowerShell/v1.0/powershell.exe';

    /**
     * Opens a new VLC window whose playlist holds every path in order and starts playing the one at $startIndex.
     * VLC has no CLI flag for a start item, so the playlist is built over its RC interface: `add` appends and plays,
     * `enqueue` only appends.
     *
     * A Windows `vlc.exe` (run from WSL) gets Windows paths, and its RC port is reached from the Windows side through
     * PowerShell, since WSL in NAT mode cannot connect to Windows' localhost.
     *
     * @param  list<string>  $paths
     */
    public function handle(array $paths, int $startIndex): void
    {
        $binary = Setting::query()->whereKey('vlc_media_player_path')->value('value') ?: 'vlc';
        $isWindows = str_ends_with(strtolower($binary), '.exe');

        if (str_contains($binary, '/') && !is_file($binary)) {
            throw new VlcLaunchException("VLC was not found at {$binary}. Check the vlc_media_player_path setting.");
        }

        $commands = collect($paths)
            ->map(fn (string $path): string => $isWindows ? $this->toWindowsPath($path) : $path)
            ->map(fn (string $path, int $index): string => ($index === $startIndex ? 'add ' : 'enqueue ').$path."\n")
            ->implode('');

        $port = $this->findFreePort();

        // Detached through nohup so VLC outlives the request.
        $result = Process::run([
            'sh', '-c', 'nohup "$@" >/dev/null 2>&1 &', 'sh',
            $binary, '--extraintf', 'rc', '--rc-host', "127.0.0.1:{$port}",
            ...($isWindows ? ['--rc-quiet'] : []),
        ]);

        if ($result->failed()) {
            throw new VlcLaunchException("VLC could not be started: {$result->errorOutput()}");
        }

        $isWindows ? $this->sendFromWindows($port, $commands) : $this->send($port, $commands);
    }

    private function toWindowsPath(string $path): string
    {
        $result = Process::run(['wslpath', '-w', $path]);

        if ($result->failed()) {
            throw new VlcLaunchException("Could not convert {$path} to a Windows path: {$result->errorOutput()}");
        }

        return trim($result->output());
    }

    private function findFreePort(): int
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');

        if ($server === false) {
            throw new VlcLaunchException('No free port for the VLC remote control interface.');
        }

        $port = (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1);
        fclose($server);

        return $port;
    }

    private function send(int $port, string $commands): void
    {
        $deadline = hrtime(true) + self::CONNECT_TIMEOUT_MS * 1_000_000;

        do {
            $socket = @stream_socket_client("tcp://127.0.0.1:{$port}", $errorCode, $errorMessage, 1);

            if ($socket !== false) {
                fwrite($socket, $commands);
                fclose($socket);

                return;
            }

            usleep(self::CONNECT_RETRY_MS * 1000);
        } while (hrtime(true) < $deadline);

        throw new VlcLaunchException('VLC did not start. Check the vlc_media_player_path setting.');
    }

    /**
     * The commands travel as base64 so paths with non-ASCII characters survive the console encoding. Windows VLC
     * drops every line after the first when they arrive together, so each one waits for its `<cmd>: returned` reply.
     */
    private function sendFromWindows(int $port, string $commands): void
    {
        $encoded = base64_encode($commands);
        $timeout = self::CONNECT_TIMEOUT_MS;
        $retry = self::CONNECT_RETRY_MS;

        $script = <<<PS
            \$deadline = (Get-Date).AddMilliseconds({$timeout})
            while (\$true) {
                try { \$client = [System.Net.Sockets.TcpClient]::new('127.0.0.1', {$port}); break }
                catch { if ((Get-Date) -gt \$deadline) { exit 1 }; Start-Sleep -Milliseconds {$retry} }
            }
            \$stream = \$client.GetStream()
            \$stream.ReadTimeout = {$timeout}
            \$reader = [System.IO.StreamReader]::new(\$stream, [System.Text.Encoding]::UTF8)
            \$commands = [System.Text.Encoding]::UTF8.GetString([Convert]::FromBase64String('{$encoded}'))
            foreach (\$command in \$commands.Split("`n", [System.StringSplitOptions]::RemoveEmptyEntries)) {
                \$bytes = [System.Text.Encoding]::UTF8.GetBytes(\$command + "`n")
                \$stream.Write(\$bytes, 0, \$bytes.Length)
                do { \$line = \$reader.ReadLine() } until (\$line -eq \$null -or \$line -match ': returned')
                if (\$line -eq \$null) { exit 1 }
            }
            \$client.Close()
            PS;

        try {
            $result = Process::timeout(30)->run([self::POWERSHELL, '-NoProfile', '-NonInteractive', '-Command', $script]);
        } catch (ProcessTimedOutException) {
            throw new VlcLaunchException('VLC did not start. Check the vlc_media_player_path setting.');
        }

        if ($result->failed()) {
            throw new VlcLaunchException('VLC did not start. Check the vlc_media_player_path setting.');
        }
    }
}
