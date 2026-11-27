<?php

namespace App\Services\QBittorrent\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * A torrent's files. 404 when qBittorrent doesn't have the torrent.
 */
final class GetTorrentFilesRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $hash) {}

    public function resolveEndpoint(): string
    {
        return '/torrents/files';
    }

    /**
     * @return array<string, string>
     */
    protected function defaultQuery(): array
    {
        return ['hash' => $this->hash];
    }
}
