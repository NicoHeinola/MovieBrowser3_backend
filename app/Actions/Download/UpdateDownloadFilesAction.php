<?php

namespace App\Actions\Download;

use App\Actions\Download\Client\DownloadOnlyPickedFilesAction;
use App\Actions\Download\Client\ResolveDownloadFolderAction;
use App\Actions\File\EnsureDiskSpaceAction;
use App\Dtos\Download\DownloadPickData;
use App\Dtos\Download\UpdateDownloadFilesData;
use App\Dtos\QBittorrent\QBittorrentFileData;
use App\Enums\ValidationErrorCode;
use App\Exceptions\DownloadNotInClientException;
use App\Exceptions\NotEnoughDiskSpaceException;
use App\Models\Download\Download;
use App\Models\DownloadPick\DownloadPick;
use App\Models\Episode\Episode;
use App\Services\QBittorrent\QBittorrentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateDownloadFilesAction
{
    use AsAction;

    public function __construct(
        private readonly QBittorrentService $qBittorrentService,
        private readonly ResolveDownloadFolderAction $resolveDownloadFolderAction,
        private readonly EnsureDiskSpaceAction $ensureDiskSpaceAction,
        private readonly DownloadOnlyPickedFilesAction $downloadOnlyPickedFilesAction,
    ) {}

    /**
     * Replaces which files of the download episodes of one show wait for, and has qBittorrent download exactly the files
     * picked by any show. One batch can feed several shows, so other shows' picks stay, and their files can't be picked
     * again. An episode gets one file: picking another one lets go of the one it waited for or got, whichever download
     * that was from.
     *
     * @throws NotEnoughDiskSpaceException When the picked files don't fit on the disk
     * @throws ValidationException When a file or episode doesn't belong here
     * @throws DownloadNotInClientException When qBittorrent no longer has the torrent
     */
    public function handle(Download $download, UpdateDownloadFilesData $data): Download
    {
        $files = $this->qBittorrentService->getTorrentFiles($download->hash);

        if ($files === null) {
            throw new DownloadNotInClientException;
        }

        $filesByIndex = collect($files)->keyBy('index');
        $newPicks = collect($data->files);
        $replacedIds = $this->getReplacedPickIds($download, $newPicks, $data->showId);
        $keptEpisodeIds = $download->picks()->whereNotIn('id', $replacedIds)->pluck('episode_id', 'file_index');

        $this->ensureFilesExist($newPicks, $filesByIndex);
        $this->ensureFilesNotPicked($newPicks, $keptEpisodeIds);
        $this->ensureEpisodesInShow($newPicks, $data->showId);
        $this->ensureDiskSpace($newPicks, $filesByIndex);

        return DB::transaction(function () use ($download, $data, $newPicks, $replacedIds, $keptEpisodeIds, $filesByIndex): Download {
            $download->update(['show_id' => $data->showId]);

            // Delete first: an episode moving to another file would clash with its own old row
            DownloadPick::query()->whereKey($replacedIds)->delete();

            foreach ($newPicks as $newPick) {
                // A pick that stays keeps its row, and with it whether it's imported
                if ($keptEpisodeIds->has($newPick->index)) {
                    continue;
                }

                $file = $filesByIndex->get($newPick->index);

                $download->picks()->create([
                    'file_index' => $newPick->index,
                    'path' => $file->path,
                    'size_bytes' => $file->sizeBytes,
                    'episode_id' => $newPick->episodeId,
                ]);
            }

            // Last, so a failure rolls the rows back
            $this->downloadOnlyPickedFilesAction->handle($download);

            return $download->fresh();
        });
    }

    /**
     * @param  Collection<int, DownloadPickData>  $newPicks
     * @param  Collection<int, QBittorrentFileData>  $filesByIndex
     */
    private function ensureFilesExist(Collection $newPicks, Collection $filesByIndex): void
    {
        foreach ($newPicks as $position => $newPick) {
            if (!$filesByIndex->has($newPick->index)) {
                throw ValidationException::withMessages(["files.{$position}.index" => ValidationErrorCode::FileIndexInvalid->value]);
            }
        }
    }

    /**
     * A file of a pick that stays (another show's, or one already imported) stays that pick's episode's.
     *
     * @param  Collection<int, DownloadPickData>  $newPicks
     * @param  Collection<int, int>  $keptEpisodeIds  Episode ids of the picks that stay, by file index
     */
    private function ensureFilesNotPicked(Collection $newPicks, Collection $keptEpisodeIds): void
    {
        foreach ($newPicks as $position => $newPick) {
            if ($keptEpisodeIds->get($newPick->index, $newPick->episodeId) !== $newPick->episodeId) {
                throw ValidationException::withMessages(["files.{$position}.index" => ValidationErrorCode::FileAlreadyPicked->value]);
            }
        }
    }

    /**
     * The picks the new picks take the place of: what the show's episodes still wait for from this download, and whatever
     * the picked episodes have from any download. A pick that a new one repeats stays.
     *
     * @param  Collection<int, DownloadPickData>  $newPicks
     * @return list<int>
     */
    private function getReplacedPickIds(Download $download, Collection $newPicks, int $showId): array
    {
        $picksToImport = $download->picks()
            ->whereNull('imported_at')
            ->whereHas('episode.entry', fn (Builder $query) => $query->where('show_id', $showId))
            ->get();
        $picksOfSameEpisodes = DownloadPick::query()->whereIn('episode_id', $newPicks->pluck('episodeId'))->get();
        $newEpisodeIds = $newPicks->pluck('episodeId', 'index');

        return $picksToImport
            ->concat($picksOfSameEpisodes)
            ->reject(fn (DownloadPick $pick): bool => $pick->download_id === $download->id
                && $newEpisodeIds->get($pick->file_index) === $pick->episode_id)
            ->modelKeys();
    }

    /**
     * @param  Collection<int, DownloadPickData>  $newPicks
     */
    private function ensureEpisodesInShow(Collection $newPicks, int $showId): void
    {
        $episodeIds = Episode::query()
            ->whereHas('entry', fn (Builder $query) => $query->where('show_id', $showId))
            ->whereKey($newPicks->pluck('episodeId'))
            ->pluck('id');

        foreach ($newPicks as $position => $newPick) {
            if (!$episodeIds->contains($newPick->episodeId)) {
                throw ValidationException::withMessages(["files.{$position}.episode_id" => ValidationErrorCode::EpisodeNotInShow->value]);
            }
        }
    }

    /**
     * What's left to download of the picked files must fit in the download folder's disk.
     *
     * @param  Collection<int, DownloadPickData>  $newPicks
     * @param  Collection<int, QBittorrentFileData>  $filesByIndex
     */
    private function ensureDiskSpace(Collection $newPicks, Collection $filesByIndex): void
    {
        $neededBytes = (int) $newPicks->sum(function (DownloadPickData $newPick) use ($filesByIndex): float {
            $file = $filesByIndex->get($newPick->index);

            return $file->sizeBytes * (1 - $file->progress);
        });
        $downloadPath = $this->resolveDownloadFolderAction->handle();

        $this->ensureDiskSpaceAction->handle($downloadPath, $neededBytes);
    }
}
