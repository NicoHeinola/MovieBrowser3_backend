<?php

namespace App\Dtos\QBittorrent;

use Spatie\LaravelData\Data;

class QBittorrentNewTorrentData extends Data
{
    /**
     * A torrent to add, stopped until its files are picked.
     *
     * @param  string  $url  A `.torrent` link, which qBittorrent reads at once, or a magnet link, whose files qBittorrent
     *                       first fetches from peers
     * @param  string  $savePath  Where qBittorrent saves it, as qBittorrent sees the folder (`D:\Downloads` on Windows)
     * @param  string  $category  The category that marks the app's torrents
     */
    public function __construct(
        public string $url,
        public string $savePath,
        public string $category,
    ) {}
}
