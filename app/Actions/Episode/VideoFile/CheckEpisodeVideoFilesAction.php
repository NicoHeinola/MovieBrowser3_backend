<?php

namespace App\Actions\Episode\VideoFile;

use App\Actions\Episode\UpdateEpisodeVideoFileSizesAction;
use App\Enums\VideoFileCheckErrorCode;
use App\Models\Episode\Episode;
use App\Models\VideoFileCheck\VideoFileCheck;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckEpisodeVideoFilesAction
{
    use AsAction;

    public const int CHUNK_SIZE = 200;

    public function __construct(
        private readonly CheckVideoBasePathExistsAction $checkVideoBasePathExistsAction,
        private readonly ResolveEpisodeVideoFileSizeAction $resolveEpisodeVideoFileSizeAction,
        private readonly UpdateEpisodeVideoFileSizesAction $updateEpisodeVideoFileSizesAction,
        private readonly UpdateVideoFileCheckProgressAction $updateVideoFileCheckProgressAction,
        private readonly UpdateVideoFileCheckResultAction $updateVideoFileCheckResultAction,
    ) {}

    /**
     * Refreshes `has_video_file` and `video_file_size` for the next chunk of episodes after $afterId.
     *
     * Returns the id to continue after, or null when the check ended: nothing left, or the base folder doesn't exist.
     * An empty base folder is a normal result: its episodes are simply missing, and the next check repairs the flags.
     */
    public function handle(VideoFileCheck $check, int $afterId = 0): ?int
    {
        if (!$this->checkVideoBasePathExistsAction->handle()) {
            return $this->stopWithoutBasePath($check);
        }

        $episodes = Episode::query()
            ->with('entry.show.titles')
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit(self::CHUNK_SIZE)
            ->get();

        // Measured before any transaction so the slow disk reads hold no lock; null marks a missing file
        $sizes = $episodes->mapWithKeys(fn (Episode $episode): array => [
            $episode->id => $this->resolveEpisodeVideoFileSizeAction->handle($episode),
        ]);

        // Checked again before writing: a folder lost during this chunk makes the rest of its files look missing
        if (!$this->checkVideoBasePathExistsAction->handle()) {
            return $this->stopWithoutBasePath($check);
        }

        $this->updateEpisodeVideoFileSizesAction->handle($episodes, $sizes);

        // Empty when the previous chunk happened to end exactly on the last episode
        if ($episodes->isNotEmpty()) {
            $this->updateVideoFileCheckProgressAction->handle($check, $episodes->last()->id);
        }

        if ($episodes->count() < self::CHUNK_SIZE) {
            $this->updateVideoFileCheckResultAction->handle($check);

            return null;
        }

        return $episodes->last()->id;
    }

    private function stopWithoutBasePath(VideoFileCheck $check): null
    {
        $this->updateVideoFileCheckResultAction->handle($check, VideoFileCheckErrorCode::VideoBasePathUnavailable);

        return null;
    }
}
