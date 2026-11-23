<?php

namespace App\Actions\Download;

use App\Actions\Episode\VideoFile\ResolveFreeDiskSpaceAction;
use App\Dtos\Download\DownloadPickData;
use App\Dtos\Download\UpdateDownloadFilesData;
use App\Dtos\QBittorrent\QBittorrentFileData;
use App\Enums\ValidationErrorCode;
use App\Exceptions\DownloadNotInClientException;
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
        private readonly ResolveDownloadPathAction $resolveDownloadPathAction,
        private readonly ResolveFreeDiskSpaceAction $resolveFreeDiskSpaceAction,
        private readonly SyncDownloadWithPicksAction $syncDownloadWithPicksAction,
    ) {}

    /**
     * Replaces which files of the download episodes of one show wait for, and has qBittorrent download exactly the files
     * picked by any show. One batch can feed several shows, so other shows' picks stay, and their files can't be
     * picked again. A pick that already is its episode's video stays too, until that episode gets another file. With no
     * files picked anywhere, the torrent stops and waits.
     *
     * Only dropping picks works without the torrent too, so an episode can let go of one qBittorrent lost.
     *
     * @throws ValidationException When a file or episode doesn't fit, or the picked files don't fit on the disk
     * @throws DownloadNotInClientException When qBittorrent no longer has the torrent
     */
    public function handle(Download $download, UpdateDownloadFilesData $data): Download
    {
        $links = collect($data->files);
        $replacedIds = $this->replacedPickIds($download, $links, $data->showId);
        $keptEpisodeIds = $download->picks()->whereNotIn('id', $replacedIds)->pluck('episode_id', 'file_index');
        // Picks that stay as they are keep their row, and with it whether they're imported or being imported
        $newLinks = $links->reject(fn (DownloadPickData $link): bool => $keptEpisodeIds->get($link->index) === $link->episodeId);

        $files = $this->qBittorrentService->getFiles($download->hash);

        if ($files === null && $newLinks->isNotEmpty()) {
            throw new DownloadNotInClientException;
        }

        $this->ensureFilesFree($links, $keptEpisodeIds);
        $this->ensureEpisodesFit($download, $links, $data->showId);

        $torrentFiles = collect($files)->keyBy('index');

        if ($newLinks->isNotEmpty()) {
            $this->ensureFilesExist($links, $torrentFiles);
            // Every link: what the kept ones still have to download needs its room too
            $this->ensureSpace($links, $torrentFiles);
        }

        return DB::transaction(function () use ($download, $data, $newLinks, $replacedIds, $files, $torrentFiles): Download {
            $download->update(['show_id' => $data->showId]);

            // Delete first: an episode moving to another file would clash with its own old row
            DownloadPick::query()->whereKey($replacedIds)->delete();

            foreach ($newLinks as $link) {
                /** @var QBittorrentFileData $file */
                $file = $torrentFiles->get($link->index);

                $download->picks()->create([
                    'file_index' => $link->index,
                    'path' => $file->path,
                    'size_bytes' => $file->sizeBytes,
                    'episode_id' => $link->episodeId,
                ]);
            }

            // Last, so a failure rolls the rows back
            if ($files !== null) {
                $this->syncDownloadWithPicksAction->handle($download);
            }

            return $download->fresh();
        });
    }

    /**
     * @param  Collection<int, DownloadPickData>  $links
     * @param  Collection<int, QBittorrentFileData>  $torrentFiles
     */
    private function ensureFilesExist(Collection $links, Collection $torrentFiles): void
    {
        foreach ($links as $position => $link) {
            if (!$torrentFiles->has($link->index)) {
                throw ValidationException::withMessages(["files.{$position}.index" => ValidationErrorCode::FileIndexInvalid->value]);
            }
        }
    }

    /**
     * A file of a pick that stays (another show's, or one already imported) stays that pick's episode's.
     *
     * @param  Collection<int, DownloadPickData>  $links
     * @param  Collection<int, int>  $keptEpisodeIds  Episode ids of the picks that stay, by file index
     */
    private function ensureFilesFree(Collection $links, Collection $keptEpisodeIds): void
    {
        foreach ($links as $position => $link) {
            if ($keptEpisodeIds->get($link->index, $link->episodeId) !== $link->episodeId) {
                throw ValidationException::withMessages(["files.{$position}.index" => ValidationErrorCode::FileAlreadyPicked->value]);
            }
        }
    }

    /**
     * The picks these links take the place of, leaving out the ones they repeat.
     *
     * @param  Collection<int, DownloadPickData>  $links
     * @return list<int>
     */
    private function replacedPickIds(Download $download, Collection $links, int $showId): array
    {
        $ofShow = fn (Builder $query) => $query->whereHas('episode.entry', fn (Builder $entry) => $entry->where('show_id', $showId));

        // What the show's episodes still wait for from this download
        $waiting = $download->picks()->whereNull('imported_at')->where($ofShow)->get();
        // What linked episodes already got, from any download: a better release takes its place
        $imported = DownloadPick::query()->whereNotNull('imported_at')->whereIn('episode_id', $links->pluck('episodeId'))->where($ofShow)->get();

        return $waiting->concat($imported)
            ->reject(fn (DownloadPick $pick): bool => $pick->download_id === $download->id && $links->contains(
                fn (DownloadPickData $link): bool => $link->index === $pick->file_index && $link->episodeId === $pick->episode_id,
            ))
            ->modelKeys();
    }

    /**
     * Each episode must belong to the show and wait for no other download's file. One that already got its file waits
     * for nothing.
     *
     * @param  Collection<int, DownloadPickData>  $links
     */
    private function ensureEpisodesFit(Download $download, Collection $links, int $showId): void
    {
        $episodeIds = $links->pluck('episodeId');
        $showIds = Episode::query()->whereIn('episodes.id', $episodeIds)->join('show_entries', 'show_entries.id', '=', 'episodes.show_entry_id')
            ->pluck('show_entries.show_id', 'episodes.id');
        $busyEpisodeIds = DownloadPick::query()->whereIn('episode_id', $episodeIds)->where('download_id', '!=', $download->id)->whereNull('imported_at')->pluck('episode_id');

        foreach ($links as $position => $link) {
            if ($showIds->get($link->episodeId) !== $showId) {
                throw ValidationException::withMessages(["files.{$position}.episode_id" => ValidationErrorCode::EpisodeNotInShow->value]);
            }

            if ($busyEpisodeIds->contains($link->episodeId)) {
                throw ValidationException::withMessages(["files.{$position}.episode_id" => ValidationErrorCode::EpisodeAlreadyDownloading->value]);
            }
        }
    }

    /**
     * What's left to download of the picked files must fit in the download folder's disk.
     *
     * @param  Collection<int, DownloadPickData>  $links
     * @param  Collection<int, QBittorrentFileData>  $torrentFiles
     */
    private function ensureSpace(Collection $links, Collection $torrentFiles): void
    {
        $needed = (int) $links->sum(function (DownloadPickData $link) use ($torrentFiles): float {
            $file = $torrentFiles->get($link->index);

            return $file->sizeBytes * (1 - $file->progress);
        });
        $free = $this->resolveFreeDiskSpaceAction->handle($this->resolveDownloadPathAction->handle());

        if ($free !== null && $needed > $free) {
            throw ValidationException::withMessages(['files' => json_encode([
                'code' => ValidationErrorCode::NotEnoughDiskSpace->value,
                // Bytes: the frontend formats them in the reader's language
                'params' => ['needed_bytes' => (string) $needed, 'free_bytes' => (string) $free],
            ], JSON_THROW_ON_ERROR)]);
        }
    }
}
