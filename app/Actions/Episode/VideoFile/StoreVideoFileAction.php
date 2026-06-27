<?php

namespace App\Actions\Episode\VideoFile;

use App\Models\Episode\Episode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreVideoFileAction
{
    use AsAction;

    public function handle(Episode $episode, UploadedFile $file, ?string $filename = null): Episode
    {
        $resolvedFilename = basename($filename ?? $file->getClientOriginalName());
        $storedFilename = $episode->videoFilename(filename: $resolvedFilename);
        $targetDirectory = $episode->videoDirectoryPath();

        File::ensureDirectoryExists($targetDirectory);
        $file->move($targetDirectory, $storedFilename);

        $episode->update(['filename' => $resolvedFilename]);

        return $episode->fresh();
    }
}
