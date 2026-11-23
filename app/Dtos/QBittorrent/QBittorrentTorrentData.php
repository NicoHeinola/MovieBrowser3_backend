<?php

namespace App\Dtos\QBittorrent;

use Spatie\LaravelData\Data;

class QBittorrentTorrentData extends Data
{
    /**
     * One torrent as qBittorrent reports it right now.
     *
     * @param  string  $state  qBittorrent's own state, such as `downloading` or `stoppedDL`
     * @param  float  $progress  0 to 1, over the files that download
     * @param  int  $sizeBytes  The size of the files that download
     * @param  int|null  $etaSeconds  Null while qBittorrent can't tell
     * @param  bool  $hasMetadata  False while a magnet's file list is still being fetched
     */
    public function __construct(
        public string $hash,
        public string $name,
        public string $state,
        public float $progress,
        public int $sizeBytes,
        public int $downloadedBytes,
        public int $downloadSpeed,
        public ?int $etaSeconds,
        public bool $hasMetadata,
    ) {}

    /**
     * Whether it finished downloading and qBittorrent stopped it: done seeding, by its share limit or by hand. Older
     * qBittorrents call the state `pausedUP`.
     */
    public function isStoppedAfterFinishing(): bool
    {
        return in_array($this->state, ['stoppedUP', 'pausedUP'], true);
    }
}
