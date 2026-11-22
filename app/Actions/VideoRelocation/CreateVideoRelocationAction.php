<?php

namespace App\Actions\VideoRelocation;

use App\Actions\Episode\VideoFile\ResolveVideoBasePathAction;
use App\Exceptions\VideoDurationScanRunningException;
use App\Exceptions\VideoFileCheckRunningException;
use App\Exceptions\VideoRelocationRunningException;
use App\Jobs\RelocateVideoFilesJob;
use App\Models\Episode\Episode;
use App\Models\VideoDurationScan\VideoDurationScan;
use App\Models\VideoFileCheck\VideoFileCheck;
use App\Models\VideoRelocation\VideoRelocation;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateVideoRelocationAction
{
    use AsAction;

    public function __construct(
        private readonly ResolveVideoBasePathAction $resolveVideoBasePathAction,
        private readonly UpdateVideoRelocationResultAction $updateVideoRelocationResultAction,
    ) {}

    /**
     * Starts moving every video file to `$toPath`; the setting follows once they all moved. With nothing to move the
     * setting is saved at once and the relocation is already finished.
     *
     * @throws VideoRelocationRunningException when another one is running
     * @throws VideoFileCheckRunningException a running check would see the moving files as missing
     * @throws VideoDurationScanRunningException a running length scan would fail to read the moving files
     */
    public function handle(string $toPath): VideoRelocation
    {
        $toPath = rtrim($toPath, '/') ?: '/';

        return DB::transaction(function () use ($toPath): VideoRelocation {
            if (VideoFileCheck::query()->active()->exists()) {
                throw new VideoFileCheckRunningException;
            }

            if (VideoDurationScan::query()->active()->exists()) {
                throw new VideoDurationScanRunningException;
            }

            $fromPath = $this->resolveVideoBasePathAction->handle();
            $total = Episode::query()->where('filename', '!=', '')->count();

            // The unique index on `running` decides who wins, so two presses at once can't both start one
            $relocation = VideoRelocation::query()->createOrFirst(['running' => true], [
                'from_path' => $fromPath,
                'to_path' => $toPath,
                'total' => $total,
            ]);

            if (!$relocation->wasRecentlyCreated) {
                throw new VideoRelocationRunningException;
            }

            if ($total === 0 || $this->isSameFolder($fromPath, $toPath)) {
                return $this->updateVideoRelocationResultAction->handle($relocation);
            }

            RelocateVideoFilesJob::dispatch($relocation)->afterCommit();

            return $relocation;
        });
    }

    /**
     * Another spelling of the current folder (`//`, a trailing `/.`, a symlink) must not count as a new one: each file
     * would be its own target, look like an earlier copy and be deleted as the original.
     */
    private function isSameFolder(string $fromPath, string $toPath): bool
    {
        $realToPath = realpath($toPath);

        return $fromPath === $toPath || ($realToPath !== false && $realToPath === realpath($fromPath));
    }
}
