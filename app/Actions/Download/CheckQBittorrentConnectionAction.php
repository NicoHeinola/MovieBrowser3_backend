<?php

namespace App\Actions\Download;

use App\Dtos\QBittorrent\QBittorrentConnectionData;
use App\Dtos\Setup\SetupCheckData;
use App\Enums\QBittorrentFailure;
use App\Enums\SetupCheckCode;
use App\Exceptions\QBittorrentUnavailableException;
use App\Services\QBittorrent\QBittorrentService;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckQBittorrentConnectionAction
{
    use AsAction;

    public function __construct(
        private readonly QBittorrentService $qBittorrentService,
    ) {}

    /**
     * Logs in to qBittorrent with the given values and checks it's new enough for the app.
     */
    public function handle(QBittorrentConnectionData $connection): SetupCheckData
    {
        if (blank($connection->url)) {
            return SetupCheckData::failed(SetupCheckCode::UrlRequired);
        }

        try {
            $version = $this->qBittorrentService->getVersion($connection);
        } catch (QBittorrentUnavailableException $exception) {
            return SetupCheckData::failed(match ($exception->failure) {
                QBittorrentFailure::Unreachable => SetupCheckCode::QBittorrentUnreachable,
                QBittorrentFailure::LoginFailed => SetupCheckCode::QBittorrentLoginFailed,
                QBittorrentFailure::Banned => SetupCheckCode::QBittorrentBanned,
            });
        }

        $minimumVersion = config('qbittorrent.minimum_version');

        if (version_compare($version, $minimumVersion, '<')) {
            return SetupCheckData::failed(SetupCheckCode::QBittorrentOutdated, ['version' => $version, 'minimum_version' => $minimumVersion]);
        }

        return SetupCheckData::passed();
    }
}
