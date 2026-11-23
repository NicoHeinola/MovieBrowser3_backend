<?php

namespace App\Dtos\Download;

use Spatie\LaravelData\Data;

class CreateDownloadData extends Data
{
    /**
     * @param  string|null  $torrentUrl  The `.torrent` link, when the site has one: qBittorrent reads its files at once
     * @param  string|null  $name  Shown in the app; null shows qBittorrent's name for the torrent
     */
    public function __construct(
        public string $magnetUrl,
        public ?string $torrentUrl,
        public ?string $name = null,
    ) {}
}
