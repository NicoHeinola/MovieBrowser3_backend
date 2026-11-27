<?php

namespace App\Actions\VideoFileCheck;

use App\Actions\Episode\VideoFile\Path\IsVideoBaseFolderAvailableAction;
use App\Dtos\VideoFile\ShowVideoFileCheckData;
use App\Exceptions\VideoBaseFolderUnavailableException;
use App\Models\Show\Show;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckShowVideoFilesAction
{
    use AsAction;

    public function __construct(
        private readonly CheckEpisodeVideoFileAction $checkEpisodeVideoFileAction,
        private readonly IsVideoBaseFolderAvailableAction $isVideoBaseFolderAvailableAction,
    ) {}

    /**
     * Checks the video files of a show's episodes right away. Each episode is saved on its own, so a check that was cut
     * off is simply repeated.
     *
     * @throws VideoBaseFolderUnavailableException When the drive isn't there
     */
    public function handle(Show $show): ShowVideoFileCheckData
    {
        if (!$this->isVideoBaseFolderAvailableAction->handle()) {
            throw new VideoBaseFolderUnavailableException;
        }

        $episodes = $show->episodes()->with('entry.show.titles')->get();

        foreach ($episodes as $episode) {
            $this->checkEpisodeVideoFileAction->handle($episode);
        }

        return new ShowVideoFileCheckData(
            missingVideoFilesCount: $show->episodes()->lostVideoFile()->count(),
            unknownVideoDurationsCount: $show->episodes()->unknownVideoDuration()->count(),
        );
    }
}
