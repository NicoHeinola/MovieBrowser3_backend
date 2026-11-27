<?php

namespace App\Actions\VideoFileCheck;

use App\Actions\Episode\VideoFile\Path\ResolveVideoBaseFolderAction;
use App\Enums\VideoFileCheckErrorCode;
use App\Models\Episode\Episode;
use App\Models\VideoFileCheck\VideoFileCheck;
use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckEpisodeVideoFilesAction
{
    use AsAction;

    public function __construct(
        private readonly CheckEpisodeVideoFileAction $checkEpisodeVideoFileAction,
        private readonly ResolveVideoBaseFolderAction $resolveVideoBaseFolderAction,
        private readonly FinishVideoFileCheckAction $finishVideoFileCheckAction,
    ) {}

    /**
     * Checks every episode's video file, and ends the check.
     */
    public function handle(VideoFileCheck $check): void
    {
        if (!$this->canLookForVideos()) {
            $this->finishVideoFileCheckAction->handle($check, VideoFileCheckErrorCode::VideoBaseFolderUnavailable);

            return;
        }

        $episodes = Episode::query()->with('entry.show.titles')->lazyById();

        foreach ($episodes as $episode) {
            $this->checkEpisodeVideoFileAction->handle($episode);
            $check->increment('checked');
        }

        $this->finishVideoFileCheckAction->handle($check);
    }

    /**
     * An empty video base folder is fine, unlike elsewhere (`IsVideoBaseFolderAvailableAction`): after its last show
     * folder was deleted by hand the check must still run, and a drive that only was away is repaired by the next one.
     */
    private function canLookForVideos(): bool
    {
        $videoBaseFolder = $this->resolveVideoBaseFolderAction->handle();

        if (File::isDirectory($videoBaseFolder) && File::isReadable($videoBaseFolder)) {
            return true;
        }

        // A library that expects no videos needs no folder: a fresh install has none until the first upload
        return !Episode::query()->withVideoFilename()->exists();
    }
}
