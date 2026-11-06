<?php

namespace App\Actions\Episode\VideoFile;

use App\Enums\VideoFileCheckErrorCode;
use App\Models\Episode\Episode;
use App\Models\VideoFileCheck\VideoFileCheck;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckEpisodeVideoFilesAction
{
    use AsAction;

    public const int CHUNK_SIZE = 200;

    public function __construct(
        private readonly CheckVideoBasePathExistsAction $checkVideoBasePathExistsAction,
        private readonly ResolveEpisodeVideoPathAction $resolveEpisodeVideoPathAction,
        private readonly UpdateVideoFileCheckProgressAction $updateVideoFileCheckProgressAction,
        private readonly UpdateVideoFileCheckResultAction $updateVideoFileCheckResultAction,
    ) {}

    /**
     * Updates `has_video_file` for the next chunk of episodes after $afterId.
     *
     * Returns the id to continue after, or null when the check ended: nothing left, or the base folder doesn't exist.
     * An empty base folder is a normal result: its episodes are simply missing, and the next check repairs the flags.
     * An episode without a filename resolves to its folder, which isFile rejects.
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

        [$found, $missing] = $episodes->partition(
            fn (Episode $episode): bool => File::isFile($this->resolveEpisodeVideoPathAction->handle($episode)),
        );

        // Checked again before writing: a folder lost during this chunk makes the rest of its files look missing
        if (!$this->checkVideoBasePathExistsAction->handle()) {
            return $this->stopWithoutBasePath($check);
        }

        // The transaction starts only now so the slow disk checks above hold no lock
        DB::transaction(function () use ($found, $missing): void {
            Episode::query()->whereKey($found->where('has_video_file', false)->pluck('id'))->update(['has_video_file' => true]);
            Episode::query()->whereKey($missing->where('has_video_file', true)->pluck('id'))->update(['has_video_file' => false]);
        });

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
