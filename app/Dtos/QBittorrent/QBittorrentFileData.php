<?php

namespace App\Dtos\QBittorrent;

use Spatie\LaravelData\Data;

class QBittorrentFileData extends Data
{
    /**
     * One file of a torrent.
     *
     * @param  int  $index  Its index in the torrent, which priorities are set by
     * @param  string  $path  Its path inside the torrent, folders separated by `/`
     * @param  float  $progress  0 to 1
     * @param  int  $priority  0 for files that don't download
     */
    public function __construct(
        public int $index,
        public string $path,
        public int $sizeBytes,
        public float $progress,
        public int $priority,
    ) {}
}
