<?php

namespace App\Dtos\QBittorrent;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

class QBittorrentFileData extends Data
{
    /**
     * One file of a torrent, as qBittorrent reports it.
     *
     * @param  string  $path  Inside the torrent, folders separated by `/`
     * @param  float  $progress  0 to 1
     */
    public function __construct(
        public int $index,
        #[MapInputName('name')]
        public string $path,
        #[MapInputName('size')]
        public int $sizeBytes,
        public float $progress,
    ) {}
}
