<?php

namespace App\Actions\Download;

use App\Enums\DownloadErrorCode;
use App\Models\Download\Download;
use Lorisleiva\Actions\Concerns\AsAction;

class ResolveDownloadGoneAction
{
    use AsAction;

    /**
     * Whether the app already knows qBittorrent no longer has the download's torrent: it was removed after seeding, or
     * lost before it finished. Asks qBittorrent nothing.
     */
    public function handle(Download $download): bool
    {
        return $download->client_removed_at !== null || $download->error_code === DownloadErrorCode::ClientLostTorrent;
    }
}
