<?php

namespace App\Actions\Episode\VideoFile;

use App\Actions\Episode\UpdateEpisodeVideoDurationsAction;
use App\Actions\VideoDurationScan\CreateVideoDurationScanAction;
use App\Actions\VideoDurationScan\UpdateVideoDurationScanProgressAction;
use App\Actions\VideoDurationScan\UpdateVideoDurationScanResultAction;
use App\Enums\VideoDurationScanErrorCode;
use App\Exceptions\VideoRelocationRunningException;
use App\Models\Episode\Episode;
use App\Models\VideoDurationScan\VideoDurationScan;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class ReadEpisodeVideoDurationsAction
{
    use AsAction;

    public const int CHUNK_SIZE = 100;

    public function __construct(
        private readonly CheckVideoBasePathExistsAction $checkVideoBasePathExistsAction,
        private readonly ResolveEpisodeVideoPathAction $resolveEpisodeVideoPathAction,
        private readonly ReadVideoFileDurationAction $readVideoFileDurationAction,
        private readonly UpdateEpisodeVideoDurationsAction $updateEpisodeVideoDurationsAction,
        private readonly UpdateVideoDurationScanProgressAction $updateVideoDurationScanProgressAction,
        private readonly UpdateVideoDurationScanResultAction $updateVideoDurationScanResultAction,
        private readonly CreateVideoDurationScanAction $createVideoDurationScanAction,
    ) {}

    /**
     * Reads the lengths of the next queued episodes, until the chunk is done or its time is up.
     *
     * Returns whether episodes still wait. An unreachable base folder ends the scan and leaves its episodes queued, so the
     * next scan picks them up once the drive is back.
     */
    public function handle(VideoDurationScan $scan): bool
    {
        if (!$this->checkVideoBasePathExistsAction->handle()) {
            return $this->stop($scan, VideoDurationScanErrorCode::VideoBasePathUnavailable);
        }

        $queued = Episode::query()
            ->with('entry.show.titles')
            ->whereNotNull('video_duration_queued_at')
            ->orderBy('id')
            ->limit(self::CHUNK_SIZE)
            ->get();

        if ($queued->isEmpty()) {
            return $this->finish($scan);
        }

        $durations = $this->readUntilDeadline($queued);
        $read = $queued->only($durations->keys()->all());

        // Checked again before writing: a drive lost during this chunk makes the rest of its files unreadable
        if (!$this->checkVideoBasePathExistsAction->handle()) {
            return $this->stop($scan, VideoDurationScanErrorCode::VideoBasePathUnavailable);
        }

        $this->updateEpisodeVideoDurationsAction->handle($read, $durations);

        $scan = $this->updateVideoDurationScanProgressAction->handle($scan, $read->count());

        if ($scan->read >= $scan->total) {
            return $this->finish($scan);
        }

        return true;
    }

    /**
     * Reads files outside any transaction, so slow disk reads hold no lock. Stops taking files once the chunk's time is
     * up, so a slow drive can't run the job into the worker's timeout; the first file is always read, so a scan moves on.
     *
     * @param  Collection<int, Episode>  $episodes
     * @return Collection<int, int|null>
     */
    private function readUntilDeadline(Collection $episodes): Collection
    {
        $deadline = microtime(true) + (int) config('video_files.duration_scan_chunk_seconds');
        $durations = collect();

        foreach ($episodes as $episode) {
            $durations[$episode->id] = $this->readVideoFileDurationAction->handle($this->resolveEpisodeVideoPathAction->handle($episode));

            if (microtime(true) >= $deadline) {
                break;
            }
        }

        return $durations;
    }

    /**
     * Ends the scan once nothing waits. An episode queued between the last count and this end joined a scan that no
     * longer reads, so a new scan takes over any still queued; with none left, starting one does nothing.
     */
    private function finish(VideoDurationScan $scan): false
    {
        $this->stop($scan);

        try {
            $this->createVideoDurationScanAction->handle();
        } catch (VideoRelocationRunningException) {
            // A folder move started in between: the episodes keep no length, and "Read missing lengths" reads them
        }

        return false;
    }

    private function stop(VideoDurationScan $scan, ?VideoDurationScanErrorCode $errorCode = null): false
    {
        $this->updateVideoDurationScanResultAction->handle($scan, $errorCode);

        return false;
    }
}
