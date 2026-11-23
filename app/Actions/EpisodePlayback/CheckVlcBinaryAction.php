<?php

namespace App\Actions\EpisodePlayback;

use App\Dtos\Setup\SetupCheckData;
use App\Enums\SetupCheckCode;
use App\Exceptions\VlcBridgeException;
use App\Services\VlcBridge\VlcBridgeService;
use Illuminate\Support\Facades\Process;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckVlcBinaryAction
{
    use AsAction;

    public function __construct(
        private readonly VlcBridgeService $vlcBridgeService,
    ) {}

    /**
     * Whether the file is called `vlc` or `vlc.exe`, so the setting can only ever start VLC, whichever launcher runs it.
     */
    public static function isVlcName(string $binary): bool
    {
        return in_array(strtolower(basename($binary)), ['vlc', 'vlc.exe'], true);
    }

    /**
     * Checks that VLC can be started from $binary, a path or a name on the PATH (`vlc` when empty), the way playback
     * would start it. With the bridge launcher the bridge checks it, because the container can't see vlc.exe.
     */
    public function handle(?string $binary): SetupCheckData
    {
        $binary = $binary ?: 'vlc';

        if (config('vlc.launcher') === 'bridge') {
            try {
                $this->vlcBridgeService->checkBinary($binary);
            } catch (VlcBridgeException $exception) {
                return SetupCheckData::failed($exception->checkCode, $exception->params);
            }

            return SetupCheckData::passed();
        }

        if (!self::isVlcName($binary)) {
            return SetupCheckData::failed(SetupCheckCode::VlcWrongBinary, ['path' => $binary]);
        }

        $found = str_contains($binary, '/')
            ? is_file($binary)
            : Process::run(['sh', '-c', 'command -v "$1"', 'sh', $binary])->successful();

        return $found ? SetupCheckData::passed() : SetupCheckData::failed(SetupCheckCode::VlcNotFound, ['path' => $binary]);
    }
}
