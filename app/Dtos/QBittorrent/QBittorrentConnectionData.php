<?php

namespace App\Dtos\QBittorrent;

use Spatie\LaravelData\Data;

class QBittorrentConnectionData extends Data
{
    /**
     * Where qBittorrent's Web UI listens and how to log in to it.
     *
     * @param  string  $url  The Web UI's address, such as `http://127.0.0.1:8080`
     */
    public function __construct(
        public string $url,
        public string $username,
        #[\SensitiveParameter]
        public string $password,
    ) {}
}
