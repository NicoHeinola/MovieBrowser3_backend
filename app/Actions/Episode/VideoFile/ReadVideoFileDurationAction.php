<?php

namespace App\Actions\Episode\VideoFile;

use getID3;
use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class ReadVideoFileDurationAction
{
    use AsAction;

    public function __construct(private readonly ReadMatroskaDurationAction $readMatroskaDurationAction) {}

    /**
     * A video's length in whole seconds, or null when it's missing or its length can't be read.
     * Matroska, almost every file here, is read from its header; other formats go through getID3.
     */
    public function handle(string $path): ?int
    {
        if (!File::isFile($path)) {
            return null;
        }

        $seconds = $this->readMatroskaDurationAction->handle($path) ?? $this->readWithGetId3($path);

        return $seconds !== null && $seconds > 0 ? (int) round($seconds) : null;
    }

    private function readWithGetId3(string $path): ?float
    {
        try {
            $analyzer = new getID3;
            // Only the length is needed: skip hashing the media and extracting cover art
            $analyzer->option_md5_data = false;
            $analyzer->option_save_attachments = false;
            $analyzer->option_extra_info = false;

            $seconds = $analyzer->analyze($path)['playtime_seconds'] ?? null;

            return is_numeric($seconds) ? (float) $seconds : null;
        } catch (Throwable) {
            return null;
        }
    }
}
