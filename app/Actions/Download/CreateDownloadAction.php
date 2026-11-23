<?php

namespace App\Actions\Download;

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
        private readonly ResolveDownloadPathAction $resolveDownloadPathAction,
        private readonly ResolveMagnetInfoHashAction $resolveMagnetInfoHashAction,
        private readonly ResolveDownloadGoneAction $resolveDownloadGoneAction,
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
        $hash = $this->resolveMagnetInfoHashAction->handle($data->magnetUrl)
            ?? throw new InvalidArgumentException('The magnet link has no info hash.');

        $existing = Download::query()->where('hash', $hash)->first();

        // The app may not know yet that the torrent is gone, so qBittorrent has the last word
        if ($existing !== null
            && !$this->resolveDownloadGoneAction->handle($existing)
            && $this->qBittorrentService->getTorrent($hash) !== null) {
            throw new DownloadExistsException($existing->id);
        }

        $isAdded = $this->qBittorrentService->addTorrent(new QBittorrentNewTorrentData(
            url: $data->torrentUrl ?? $data->magnetUrl,
            savePath: $this->resolveDownloadPathAction->handle(forClient: true),
            category: config('qbittorrent.category'),
        ));

        // qBittorrent also turns down a torrent it already has, such as one the user added by hand: that one is taken
        // over as it is. Either way it must show up, which takes a moment while qBittorrent reads a `.torrent` link.
        if (!$this->waitForTorrent($hash, $isAdded)) {
            throw new QBittorrentUnavailableException(QBittorrentFailure::TorrentRejected);
        }

        // After qBittorrent has it: an add can't be rolled back, but a row without a torrent would be worse
        return DB::transaction(function () use ($data, $existing, $hash): Download {
            if ($existing !== null) {
                return $this->restart($existing, $data->magnetUrl);
            }

            return Download::query()->create([
                'hash' => $hash,
                // Characters, not display width: the column holds 255 characters, Japanese ones too
                'name' => $data->name === null ? null : mb_substr(trim($data->name), 0, 255),
                'magnet_url' => $data->magnetUrl,
                'status' => DownloadStatus::WaitingForFiles,
            ]);
        });
    }

    /**
     * Starts a download over whose torrent was added again. Its picks go: a kept one would download its file again,
     * even one that already is its episode's video. Episodes keep their videos.
     */
    private function restart(Download $download, string $magnetUrl): Download
    {
        $download->picks()->delete();
        $download->update([
            'magnet_url' => $magnetUrl,
            'status' => DownloadStatus::WaitingForFiles,
            'error_code' => null,
            'client_removed_at' => null,
        ]);

        return $download->fresh();
    }

    /**
     * Whether qBittorrent lists the torrent, asking a few times while a fresh add is still being read.
     *
     * @param  bool  $isAdded  Whether qBittorrent took the add; a turned-down one is only looked up once
     */
    private function waitForTorrent(string $hash, bool $isAdded): bool
    {
        $attempts = $isAdded ? config('qbittorrent.add_wait_attempts') : 1;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            if ($this->qBittorrentService->getTorrent($hash) !== null) {
                return true;
            }

            if ($attempt < $attempts) {
                Sleep::for(500)->milliseconds();
            }
        }

        return false;
    }
}
