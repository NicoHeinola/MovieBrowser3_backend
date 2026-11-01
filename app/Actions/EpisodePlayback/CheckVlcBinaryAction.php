<?php

namespace App\Actions\EpisodePlayback;

use App\Exceptions\VlcLaunchException;
use Illuminate\Support\Facades\Process;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckVlcBinaryAction
{
    use AsAction;

    public function __construct(
        private readonly RequestVlcBridgeAction $requestVlcBridgeAction,
    ) {}

    /**
     * Checks that VLC can be started from $binary, a path or a name on the PATH (`vlc` when empty), the way playback
     * would start it. With the bridge launcher the bridge checks it, because the container can't see vlc.exe.
     *
     * @return string|null Why VLC can't be started from there, or null when it can.
     */
    public function handle(?string $binary): ?string
    {
        $binary = $binary ?: 'vlc';

        if (config('vlc.launcher') === 'bridge') {
            try {
                $this->requestVlcBridgeAction->handle('check-binary', ['binary' => $binary]);
            } catch (VlcLaunchException $exception) {
                return $exception->getMessage();
            }

            return null;
        }

        if (!in_array(strtolower(basename($binary)), ['vlc', 'vlc.exe'], true)) {
            return "{$binary} is not VLC. Only vlc or vlc.exe can be started.";
        }

        $found = str_contains($binary, '/')
            ? is_file($binary)
            : Process::run(['sh', '-c', 'command -v "$1"', 'sh', $binary])->successful();

        return $found ? null : "VLC was not found at {$binary}.";
    }
}
