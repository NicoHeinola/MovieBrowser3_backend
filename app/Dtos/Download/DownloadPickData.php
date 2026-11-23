<?php

namespace App\Dtos\Download;

use Spatie\LaravelData\Data;

class DownloadPickData extends Data
{
    /**
     * One torrent file picked for an episode.
     *
     * @param  int  $index  The file's index in the torrent
     */
    public function __construct(
        public int $index,
        public int $episodeId,
    ) {}
}
