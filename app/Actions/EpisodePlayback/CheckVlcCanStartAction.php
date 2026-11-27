<?php

namespace App\Actions\EpisodePlayback;

use App\Dtos\Setup\SetupCheckData;
use App\Enums\SetupCheckCode;
use App\Exceptions\HostBridgeException;
use App\Services\HostBridge\HostBridgeService;
use Illuminate\Support\Facades\Process;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckVlcCanStartAction
{
    use AsAction;

    public function __construct(
        private readonly HostBridgeService $hostBridgeService,
    ) {}

    /**
     * Whether the file is called `vlc` or `vlc.exe`, so the setting can only ever start VLC, whoever starts it.
     */
    public static function isVlcFilename(string $binary): bool
    {
        return in_array(strtolower(basename($binary)), ['vlc', 'vlc.exe'], true);
    }

    /**
     * Checks that VLC can be started, the way playback would start it. With the host bridge the bridge checks it, because
     * the container can't see vlc.exe.
     *
     * @param  string|null  $binary  A path, or a name on the PATH; `vlc` when empty
     */
    public function handle(?string $binary): SetupCheckData
    {
        $binary = $binary ?: 'vlc';

        if (config('host_bridge.enabled')) {
            return $this->checkThroughBridge($binary);
        }

        if (!self::isVlcFilename($binary)) {
            return SetupCheckData::failed(SetupCheckCode::VlcWrongBinary, ['path' => $binary]);
        }

        if (!$this->isBinaryInstalled($binary)) {
            return SetupCheckData::failed(SetupCheckCode::VlcNotFound, ['path' => $binary]);
        }

        return SetupCheckData::passed();
    }

    private function checkThroughBridge(string $binary): SetupCheckData
    {
        try {
            $this->hostBridgeService->checkVlcCanStart($binary);
        } catch (HostBridgeException $exception) {
            return SetupCheckData::failed($exception->errorCode, $exception->params);
        }

        return SetupCheckData::passed();
    }

    private function isBinaryInstalled(string $binary): bool
    {
        if (str_contains($binary, '/')) {
            return is_file($binary);
        }

        return Process::run(['sh', '-c', 'command -v "$1"', 'sh', $binary])->successful();
    }
}
