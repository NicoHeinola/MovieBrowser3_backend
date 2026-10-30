<?php

namespace App\Actions\Episode\VideoFile;

use App\Models\Episode\Episode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreVideoFileAction
{
    use AsAction;

    public function __construct(private readonly ResolveEpisodeVideoPathAction $resolveEpisodeVideoPathAction) {}

    public function handle(Episode $episode, UploadedFile $file, ?string $filename = null): Episode
    {
        return DB::transaction(function () use ($episode, $file, $filename): Episode {
            $resolvedFilename = basename($filename ?? $file->getClientOriginalName());
            $targetPath = $this->resolveEpisodeVideoPathAction->handle($episode, filename: $resolvedFilename);

            File::ensureDirectoryExists(dirname($targetPath));
            $file->move(dirname($targetPath), basename($targetPath));

            $episode->update(['filename' => $resolvedFilename]);

            return $episode->fresh();
        });
    }
}
