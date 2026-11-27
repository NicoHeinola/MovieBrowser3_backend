<?php

namespace App\Actions\Episode\VideoFile;

use getID3;
use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class ReadVideoFileDurationAction
{
    use AsAction;

    /**
     * A video's length in whole seconds, or null when it's missing or its length can't be read.
     */
    public function handle(string $path): ?int
    {
        if (!File::isFile($path)) {
            return null;
        }

        try {
            $details = $this->analyzeFile($path);
        } catch (Throwable) {
            return null;
        }

        $seconds = (int) round($details['playtime_seconds'] ?? 0);

        if ($seconds <= 0) {
            return null;
        }

        return $seconds;
    }

    /**
     * @return array<string, mixed>
     */
    private function analyzeFile(string $path): array
    {
        $analyzer = new getID3;
        // Only the length is needed: skip hashing the media and extracting cover art
        $analyzer->option_md5_data = false;
        $analyzer->option_save_attachments = false;
        $analyzer->option_extra_info = false;

        return $analyzer->analyze($path);
    }
}
