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
use Illuminate\Database\Eloquent\Relations\HasMany;
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
     * Replaces which files of the download become episodes of one show, and has qBittorrent download exactly the files
     * picked by any show. One batch can feed several shows, so other shows' picks stay, and their files can't be
     * picked again. With no files picked anywhere, the torrent stops and waits.
     *
     * @throws ValidationException When a file or episode doesn't fit, or the picked files don't fit on the disk
     * @throws DownloadNotInClientException When qBittorrent no longer has the torrent
     */
    public function handle(Download $download, UpdateDownloadFilesData $data): Download
    {
        $torrentFiles = collect($this->qBittorrentService->getFiles($download->hash) ?? throw new DownloadNotInClientException)
            ->keyBy('index');
        $links = collect($data->files);

        $this->ensureFilesExist($links, $torrentFiles);
        $this->ensureFilesFree($download, $links, $data->showId);
        $this->ensureEpisodesFit($download, $links, $data->showId);
        $this->ensureSpace($links, $torrentFiles);

        return DB::transaction(function () use ($download, $data, $links, $torrentFiles): Download {
            $download->update(['show_id' => $data->showId]);

            // Delete first: an episode moving to another file of the same torrent would clash with its own old row
            $this->showPicks($download, $data->showId)->delete();

            foreach ($links as $link) {
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
            $this->syncDownloadWithPicksAction->handle($download);

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
     * A file another show's episode waits for stays that episode's.
     *
     * @param  Collection<int, DownloadPickData>  $links
     */
    private function ensureFilesFree(Download $download, Collection $links, int $showId): void
    {
        $takenIndexes = $download->picks()->whereNotIn('id', $this->showPicks($download, $showId)->select('id'))->pluck('file_index');

        foreach ($links as $position => $link) {
            if ($takenIndexes->contains($link->index)) {
                throw ValidationException::withMessages(["files.{$position}.index" => ValidationErrorCode::FileAlreadyPicked->value]);
            }
        }
    }

    /**
     * The download's picks for episodes of one show.
     *
     * @return HasMany<DownloadPick, Download>
     */
    private function showPicks(Download $download, int $showId): HasMany
    {
        return $download->picks()->whereHas('episode.entry', fn (Builder $query) => $query->where('show_id', $showId));
    }

    /**
     * Each episode must belong to the show and wait for no other download's file.
     *
     * @param  Collection<int, DownloadPickData>  $links
     */
    private function ensureEpisodesFit(Download $download, Collection $links, int $showId): void
    {
        $episodeIds = $links->pluck('episodeId');
        $showIds = Episode::query()->whereIn('episodes.id', $episodeIds)->join('show_entries', 'show_entries.id', '=', 'episodes.show_entry_id')
            ->pluck('show_entries.show_id', 'episodes.id');
        $busyEpisodeIds = DownloadPick::query()->whereIn('episode_id', $episodeIds)->where('download_id', '!=', $download->id)->pluck('episode_id');

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
