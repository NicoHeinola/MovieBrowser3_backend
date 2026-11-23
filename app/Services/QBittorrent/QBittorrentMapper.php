<?php

namespace App\Services\QBittorrent;

use App\Dtos\QBittorrent\QBittorrentFileData;
use App\Dtos\QBittorrent\QBittorrentTorrentData;

/**
 * Translates qBittorrent's Web API answers into the app's DTOs. Only `QBittorrentService` uses it.
 */
final class QBittorrentMapper
{
    /** qBittorrent's ETA when it can't tell: 100 days */
    private const int UNKNOWN_ETA = 8_640_000;

    /**
     * @param  array<string, mixed>  $torrent  One item of `/torrents/info`
     */
    public function torrent(array $torrent): QBittorrentTorrentData
    {
        $eta = (int) ($torrent['eta'] ?? self::UNKNOWN_ETA);

        return new QBittorrentTorrentData(
            hash: strtolower((string) $torrent['hash']),
            name: (string) ($torrent['name'] ?? ''),
            state: (string) ($torrent['state'] ?? ''),
            progress: (float) ($torrent['progress'] ?? 0),
            sizeBytes: (int) ($torrent['size'] ?? 0),
            downloadedBytes: (int) ($torrent['downloaded'] ?? 0),
            downloadSpeed: (int) ($torrent['dlspeed'] ?? 0),
            etaSeconds: $eta >= self::UNKNOWN_ETA ? null : $eta,
            hasMetadata: (bool) ($torrent['has_metadata'] ?? true),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $files  `/torrents/files`
     * @return list<QBittorrentFileData>
     */
    public function files(array $files): array
    {
        return array_map(fn (array $file): QBittorrentFileData => new QBittorrentFileData(
            index: (int) $file['index'],
            path: (string) $file['name'],
            sizeBytes: (int) ($file['size'] ?? 0),
            progress: (float) ($file['progress'] ?? 0),
            priority: (int) ($file['priority'] ?? 1),
        ), $files);
    }
}
