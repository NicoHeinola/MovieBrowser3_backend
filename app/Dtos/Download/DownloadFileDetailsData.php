<?php

namespace App\Dtos\Download;

use Spatie\LaravelData\Data;

class DownloadFileDetailsData extends Data
{
    /**
     * One file of a download's torrent, with the episode it's picked for.
     *
     * @param  float  $progress  0 to 1
     * @param  int|null  $showId  The show of that episode, so a show's editor leaves other shows' picks alone
     */
    public function __construct(
        public int $index,
        public string $path,
        public int $sizeBytes,
        public float $progress,
        public ?int $episodeId,
        public ?int $showId,
    ) {}
}
