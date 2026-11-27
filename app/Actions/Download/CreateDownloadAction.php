<?php

namespace App\Actions\Download;

use App\Actions\Download\Client\IsDownloadGoneAction;
use App\Actions\Download\Client\ResolveDownloadFolderAction;
use App\Actions\Download\Client\ResolveMagnetInfoHashAction;
use App\Dtos\Download\CreateDownloadData;
use App\Dtos\QBittorrent\QBittorrentNewTorrentData;
use App\Enums\DownloadStatus;
use App\Enums\QBittorrentFailure;
use App\Exceptions\DownloadClientNotConfiguredException;
use App\Exceptions\DownloadExistsException;
use App\Exceptions\QBittorrentUnavailableException;
use App\Models\Download\Download;
use App\Services\QBittorrent\QBittorrentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateDownloadAction
{
    use AsAction;

    public function __construct(
        private readonly QBittorrentService $qBittorrentService,
        private readonly ResolveDownloadFolderAction $resolveDownloadFolderAction,
        private readonly ResolveMagnetInfoHashAction $resolveMagnetInfoHashAction,
        private readonly IsDownloadGoneAction $isDownloadGoneAction,
    ) {}

    /**
     * Hands the torrent to qBittorrent, which holds it without downloading until files are picked for episodes. A
     * download qBittorrent no longer has is added again and starts over, without its old picks.
     *
     * @throws DownloadClientNotConfiguredException While downloads are off
     * @throws DownloadExistsException When the torrent is already a download
     * @throws QBittorrentUnavailableException When qBittorrent can't be used or turns the torrent down
     */
    public function handle(CreateDownloadData $data): Download
    {
        $hash = $this->resolveMagnetInfoHashAction->handle($data->magnetUrl);

        if ($hash === null) {
            throw new InvalidArgumentException('The magnet link has no info hash.');
        }

        $existingDownload = Download::query()->where('hash', $hash)->first();

        if ($existingDownload !== null && $this->isTorrentInClient($existingDownload)) {
            throw new DownloadExistsException($existingDownload->id);
        }

        $this->qBittorrentService->addTorrent(new QBittorrentNewTorrentData(
            url: $data->torrentUrl ?? $data->magnetUrl,
            savePath: $this->resolveDownloadFolderAction->handle(isForClient: true),
            category: config('qbittorrent.category'),
        ));

        // One qBittorrent already had, such as one the user added by hand, is taken over as it is
        if (!$this->isTorrentListedSoon($hash)) {
            throw new QBittorrentUnavailableException(QBittorrentFailure::TorrentRejected);
        }

        if ($existingDownload !== null) {
            return $this->restartDownload($existingDownload, $data->magnetUrl);
        }

        // After qBittorrent has it: an add can't be rolled back, but a row without a torrent would be worse
        return Download::query()->create([
            'hash' => $hash,
            'name' => $this->shortenName($data->name),
            'magnet_url' => $data->magnetUrl,
            'status' => DownloadStatus::WaitingForFiles,
        ]);
    }

    /**
     * Starts a download over whose torrent was added again. Its picks are deleted: a kept one would download its file
     * again, even one that already is its episode's video. Episodes keep their videos.
     */
    private function restartDownload(Download $download, string $magnetUrl): Download
    {
        return DB::transaction(function () use ($download, $magnetUrl): Download {
            $download->picks()->delete();
            $download->update([
                'magnet_url' => $magnetUrl,
                'status' => DownloadStatus::WaitingForFiles,
                'error_code' => null,
                'client_removed_at' => null,
            ]);

            return $download->fresh();
        });
    }

    /**
     * The column holds 255 characters, Japanese ones too.
     */
    private function shortenName(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        return mb_substr(trim($name), 0, 255);
    }

    /**
     * Whether qBittorrent has the torrent. The app may not know yet that it's gone, so qBittorrent has the last word.
     */
    private function isTorrentInClient(Download $download): bool
    {
        if ($this->isDownloadGoneAction->handle($download)) {
            return false;
        }

        return $this->qBittorrentService->getTorrent($download->hash) !== null;
    }

    /**
     * Whether qBittorrent lists the torrent within a few seconds: it first reads a `.torrent` link.
     */
    private function isTorrentListedSoon(string $hash): bool
    {
        for ($attempt = 1; $attempt <= config('qbittorrent.add_wait_attempts'); $attempt++) {
            if ($this->qBittorrentService->getTorrent($hash) !== null) {
                return true;
            }

            Sleep::for(500)->milliseconds();
        }

        return false;
    }
}
