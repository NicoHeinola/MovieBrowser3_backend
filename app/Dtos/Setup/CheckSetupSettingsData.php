<?php

namespace App\Dtos\Setup;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * The setup values to check. Only the values that were sent are checked. The qBittorrent values are checked together,
 * as one login, whenever its address is sent; a password left out means the saved one.
 */
class CheckSetupSettingsData extends Data
{
    public function __construct(
        public string|Optional|null $videoBaseFolder,
        public string|Optional|null $vlcMediaPlayerPath,
        public string|Optional|null $qbittorrentUrl,
        public string|Optional|null $qbittorrentUsername,
        #[\SensitiveParameter]
        public string|Optional|null $qbittorrentPassword,
        public string|Optional|null $downloadPath,
    ) {}
}
