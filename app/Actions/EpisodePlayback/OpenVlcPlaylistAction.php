<?php

namespace App\Actions\EpisodePlayback;

use App\Dtos\EpisodePlayback\VlcConnectionData;
use App\Exceptions\VlcLaunchException;
use App\Models\Setting\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class OpenVlcPlaylistAction
{
    use AsAction;

    // VLC usually answers within a second; a cold start on Windows gets up to 10 seconds (100 x 100 ms).
    private const int STARTUP_ATTEMPTS = 100;

    private const int STARTUP_ATTEMPT_DELAY_MS = 100;

    // VLC's web interface port. It must stay outside Linux's ephemeral range (40525-44620 here): with WSL mirrored
    // networking that range belongs to Linux, so a Windows vlc.exe cannot listen on it. It also stays below Windows'
    // own dynamic range (49152 and up).
    private const int PORT_RANGE_START = 47000;

    private const int PORT_RANGE_END = 47999;

    public function __construct(
        private readonly RequestVlcStatusAction $requestVlcStatusAction,
    ) {}

    /**
     * Opens a new VLC window whose playlist holds every path in order and plays the one at $startIndex.
     *
     * VLC has no command-line flag for a start item, so it is started with its web interface and the playlist is
     * built through that: `in_enqueue` appends a file, `in_play` appends it and starts playing it.
     *
     * @param  list<string>  $paths
     * @return VlcConnectionData How to reach this VLC window afterwards, e.g. to track what it is playing.
     */
    public function handle(array $paths, int $startIndex): VlcConnectionData
    {
        $binary = $this->resolveBinary();
        $vlc = new VlcConnectionData($this->findFreePort(), Str::random(32));

        $this->launch($binary, $vlc);
        $this->waitUntilReady($vlc);

        foreach ($paths as $index => $path) {
            $status = $this->requestVlcStatusAction->handle($vlc, [
                'command' => $index === $startIndex ? 'in_play' : 'in_enqueue',
                'input' => $this->isWindowsBinary($binary) ? $this->toWindowsPath($path) : $path,
            ]);

            if ($status === null) {
                throw new VlcLaunchException('VLC was closed before the playlist was built.');
            }
        }

        return $vlc;
    }

    /**
     * The VLC executable comes from the vlc_media_player_path setting, or `vlc` on the PATH when it is empty.
     */
    private function resolveBinary(): string
    {
        $binary = Setting::query()->whereKey('vlc_media_player_path')->value('value') ?: 'vlc';

        if (str_contains($binary, '/') && !is_file($binary)) {
            throw new VlcLaunchException("VLC was not found at {$binary}. Check the vlc_media_player_path setting.");
        }

        return $binary;
    }

    /**
     * Starts VLC with its web interface on localhost only. It runs detached (nohup, in the background) so the window
     * stays open after this request finishes.
     */
    private function launch(string $binary, VlcConnectionData $vlc): void
    {
        $result = Process::run([
            'sh', '-c', 'nohup "$@" </dev/null >/dev/null 2>&1 &', 'sh',
            $binary,
            '--extraintf', 'http',
            '--http-host', '127.0.0.1',
            '--http-port', (string) $vlc->port,
            '--http-password', $vlc->password,
        ]);

        if ($result->failed()) {
            throw new VlcLaunchException("VLC could not be started: {$result->errorOutput()}");
        }
    }

    /**
     * VLC needs a moment before its web interface answers; keep asking until it does.
     */
    private function waitUntilReady(VlcConnectionData $vlc): void
    {
        for ($attempt = 0; $attempt < self::STARTUP_ATTEMPTS; $attempt++) {
            if ($this->requestVlcStatusAction->handle($vlc) !== null) {
                return;
            }

            Sleep::for(self::STARTUP_ATTEMPT_DELAY_MS)->milliseconds();
        }

        throw new VlcLaunchException('VLC did not start. Check the vlc_media_player_path setting.');
    }

    /**
     * Picks a random port from the VLC port range that nothing listens on yet. It only tries to connect: opening the
     * port here, even for a moment, keeps a Windows vlc.exe from listening on it under WSL mirrored networking.
     * Random order keeps a still-open VLC window from an earlier playback out of the way.
     */
    private function findFreePort(): int
    {
        foreach (Arr::shuffle(range(self::PORT_RANGE_START, self::PORT_RANGE_END)) as $port) {
            $connection = @stream_socket_client("tcp://127.0.0.1:{$port}", $errorCode, $errorMessage, 0.2);

            if ($connection === false) {
                return $port;
            }

            fclose($connection);
        }

        throw new VlcLaunchException('No free port for the VLC web interface.');
    }

    /**
     * A Windows vlc.exe started from WSL cannot open Linux paths, so its files need their Windows form.
     */
    private function isWindowsBinary(string $binary): bool
    {
        return str_ends_with(strtolower($binary), '.exe');
    }

    private function toWindowsPath(string $path): string
    {
        $result = Process::run(['wslpath', '-w', $path]);

        if ($result->failed()) {
            throw new VlcLaunchException("Could not convert {$path} to a Windows path: {$result->errorOutput()}");
        }

        return trim($result->output());
    }
}
