<?php

namespace App\Actions\EpisodePlayback;

use App\Dtos\EpisodePlayback\VlcConnectionData;
use App\Enums\SettingKey;
use App\Enums\VlcLaunchCode;
use App\Exceptions\VlcLaunchException;
use App\Models\Setting\Setting;
use App\Services\HostBridge\HostBridgeService;
use App\Services\Vlc\VlcService;
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

    // VLC's web interface port, below Windows' own dynamic range (49152 and up). Linux's ephemeral range is skipped
    // when it's picked: with WSL mirrored networking those ports belong to Linux, so a Windows vlc.exe can't listen
    // on them, and WSL moves that range on every boot (40525-44620 once, 44620-48715 another time).
    private const int PORT_RANGE_START = 40000;

    private const int PORT_RANGE_END = 49151;

    public function __construct(
        private readonly HostBridgeService $hostBridgeService,
        private readonly ResolveEphemeralPortRangeAction $resolveEphemeralPortRangeAction,
    ) {}

    /**
     * Opens a new VLC window whose playlist holds every path in order, and plays one of them.
     *
     * VLC has no command-line flag for a start item, so it is started with its web interface and the playlist is
     * built through that (`VlcService`).
     *
     * With the host bridge (host_bridge.enabled) the bridge starts VLC and converts the paths, because a
     * containerized backend can neither see vlc.exe nor run wslpath.
     *
     * @param  list<string>  $paths
     * @param  int  $startIndex  Which of the paths plays first
     * @param  int  $startSeconds  How far into it playback starts
     * @return VlcConnectionData How to reach this VLC window afterwards, e.g. to track what it is playing.
     */
    public function handle(array $paths, int $startIndex, int $startSeconds = 0): VlcConnectionData
    {
        $binary = $this->resolveVlcBinary();
        $port = $this->findFreePort();
        $vlcConnection = new VlcConnectionData($port, password: Str::random(32));

        if ($this->isWindowsBinary($binary)) {
            $paths = $this->toWindowsPaths($paths);
        }

        $this->launchVlc($binary, $vlcConnection);

        $vlcService = new VlcService($vlcConnection);
        $this->waitUntilVlcReady($vlcService);

        foreach ($paths as $index => $path) {
            if ($index === $startIndex) {
                $isAdded = $vlcService->playFile($path, $startSeconds);
            } else {
                $isAdded = $vlcService->enqueueFile($path);
            }

            if (!$isAdded) {
                throw new VlcLaunchException('VLC was closed before the playlist was built.', VlcLaunchCode::VlcClosedEarly);
            }
        }

        return $vlcConnection;
    }

    /**
     * VLC's path from the settings, or `vlc` on the PATH when it's empty.
     */
    private function resolveVlcBinary(): string
    {
        return Setting::query()->whereKey(SettingKey::VlcMediaPlayerPath->value)->value('value') ?: 'vlc';
    }

    /**
     * Starts VLC with its web interface on localhost only. It runs detached (nohup, in the background) so the window
     * stays open after this request finishes.
     */
    private function launchVlc(string $binary, VlcConnectionData $vlcConnection): void
    {
        if (config('host_bridge.enabled')) {
            $this->hostBridgeService->launchVlc($binary, $vlcConnection->port, $vlcConnection->password);

            return;
        }

        $result = Process::run([
            'sh', '-c', 'nohup "$@" </dev/null >/dev/null 2>&1 &', 'sh',
            $binary,
            '--extraintf', 'http',
            '--http-host', '127.0.0.1',
            '--http-port', (string) $vlcConnection->port,
            '--http-password', $vlcConnection->password,
        ]);

        if ($result->failed()) {
            throw new VlcLaunchException(
                "VLC could not be started: {$result->errorOutput()}",
                VlcLaunchCode::VlcStartFailed,
                ['detail' => $result->errorOutput()],
            );
        }
    }

    /**
     * VLC needs a moment before its web interface answers; keep asking until it does.
     */
    private function waitUntilVlcReady(VlcService $vlcService): void
    {
        for ($attempt = 0; $attempt < self::STARTUP_ATTEMPTS; $attempt++) {
            if ($vlcService->getStatus() !== null) {
                return;
            }

            Sleep::for(self::STARTUP_ATTEMPT_DELAY_MS)->milliseconds();
        }

        throw new VlcLaunchException('VLC did not start. Check the vlc_media_player_path setting.', VlcLaunchCode::VlcStartTimeout);
    }

    /**
     * Picks a random port from the VLC port range that nothing listens on yet. It only tries to connect: opening the
     * port here, even for a moment, keeps a Windows vlc.exe from listening on it under WSL mirrored networking.
     * Random order keeps a still-open VLC window from an earlier playback out of the way.
     */
    private function findFreePort(): int
    {
        $ephemeralPorts = $this->resolveEphemeralPortRangeAction->handle();
        $ports = array_filter(
            range(self::PORT_RANGE_START, self::PORT_RANGE_END),
            fn (int $port): bool => $ephemeralPorts === null || $port < $ephemeralPorts[0] || $port > $ephemeralPorts[1],
        );

        foreach (Arr::shuffle($ports) as $port) {
            $connection = @stream_socket_client("tcp://127.0.0.1:{$port}", $errorCode, $errorMessage, 0.2);

            if ($connection === false) {
                return $port;
            }

            fclose($connection);
        }

        throw new VlcLaunchException('No free port for the VLC web interface.', VlcLaunchCode::VlcNoFreePort);
    }

    /**
     * A Windows vlc.exe started from WSL cannot open Linux paths, so its files need their Windows form.
     */
    private function isWindowsBinary(string $binary): bool
    {
        return str_ends_with(strtolower($binary), '.exe');
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function toWindowsPaths(array $paths): array
    {
        if (config('host_bridge.enabled')) {
            return $this->hostBridgeService->toWindowsPaths($paths);
        }

        return array_map(function (string $path): string {
            $result = Process::run(['wslpath', '-w', $path]);

            if ($result->failed()) {
                throw new VlcLaunchException(
                    "Could not convert {$path} to a Windows path: {$result->errorOutput()}",
                    VlcLaunchCode::VlcPathConversionFailed,
                    ['path' => $path, 'detail' => $result->errorOutput()],
                );
            }

            return trim($result->output());
        }, $paths);
    }
}
