<?php

namespace App\Actions\VideoFileCheck;

use App\Actions\Episode\VideoFile\Path\ResolveEpisodeVideoPathAction;
use App\Actions\Episode\VideoFile\ReadVideoFileDurationAction;
use App\Models\Episode\Episode;
use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckEpisodeVideoFileAction
{
    use AsAction;

    public function __construct(
        private readonly ReadVideoFileDurationAction $readVideoFileDurationAction,
        private readonly ResolveEpisodeVideoPathAction $resolveEpisodeVideoPathAction,
    ) {}

    /**
     * Records what is on the disk for an episode: whether its file is there, its size and its length. The length is read
     * only when it's unknown or the file isn't the one measured before. Without the drive every file looks missing, so
     * callers make sure it's there first.
     */
    public function handle(Episode $episode): Episode
    {
        $path = $this->resolveEpisodeVideoPathAction->handle($episode);

        // Without a filename the path is the episode's folder, which is no file either
        if (!File::isFile($path)) {
            // Its size and length stay, so a drive that was only away needs no reading again
            $episode->update(['has_video_file' => false]);

            return $episode;
        }

        $size = File::size($path);
        $isReplaced = $episode->video_file_size !== null && $episode->video_file_size !== $size;
        $episode->fill(['has_video_file' => true, 'video_file_size' => $size]);

        if ($isReplaced || $episode->video_duration_seconds === null) {
            $episode->video_duration_seconds = $this->readVideoFileDurationAction->handle($path);
        }

        // Writes nothing when nothing changed
        $episode->save();

        return $episode;
    }
}
