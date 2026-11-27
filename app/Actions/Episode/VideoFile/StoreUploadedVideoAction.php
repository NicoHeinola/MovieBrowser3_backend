<?php

namespace App\Actions\Episode\VideoFile;

use App\Actions\Episode\VideoFile\Path\IsVideoBaseFolderAvailableAction;
use App\Actions\Episode\VideoFile\Path\ResolveEpisodeVideoPathAction;
use App\Exceptions\VideoBaseFolderUnavailableException;
use App\Models\Episode\Episode;
use Illuminate\Http\UploadedFile;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class StoreUploadedVideoAction
{
    use AsAction;

    public function __construct(
        private readonly DeleteVideoFileAction $deleteVideoFileAction,
        private readonly IsVideoBaseFolderAvailableAction $isVideoBaseFolderAvailableAction,
        private readonly ReplaceEpisodeVideoAction $replaceEpisodeVideoAction,
        private readonly ResolveEpisodeVideoPathAction $resolveEpisodeVideoPathAction,
    ) {}

    /**
     * Makes an uploaded file the episode's video, in place of the one it had.
     *
     * @throws VideoBaseFolderUnavailableException When the video drive is not there or a folder move runs
     */
    public function handle(Episode $episode, UploadedFile $file): Episode
    {
        // Without the drive, the file would go into the container's empty folder and not onto the drive
        if (!$this->isVideoBaseFolderAvailableAction->handle()) {
            throw new VideoBaseFolderUnavailableException;
        }

        $filename = basename($file->getClientOriginalName());
        $videoPath = $this->resolveEpisodeVideoPathAction->handle($episode, filename: $filename);
        // Under a name of its own next to where it goes, so the old video stays until the new one is complete
        $uploadPath = dirname($videoPath)."/{$episode->id}.uploading";

        try {
            $file->move(dirname($uploadPath), basename($uploadPath));

            return $this->replaceEpisodeVideoAction->handle($episode, $uploadPath, $filename);
        } catch (Throwable $exception) {
            $this->deleteVideoFileAction->handle($uploadPath);

            throw $exception;
        }
    }
}
