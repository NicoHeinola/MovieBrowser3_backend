<?php

namespace App\Actions\Episode\VideoFile;

use Illuminate\Support\Facades\Process;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckWindowsCopyAvailableAction
{
    use AsAction;

    /**
     * Whether Windows' robocopy can copy videos (`CopyVideoFileAction`'s `$copyWithWindows`): only when the app runs in
     * WSL itself, not in Docker. Asked once per request or job.
     */
    public function handle(): bool
    {
        return once(fn (): bool => Process::run(['sh', '-c', 'command -v robocopy.exe'])->successful());
    }
}
