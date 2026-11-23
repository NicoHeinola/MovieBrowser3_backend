<?php

namespace App\Services\QBittorrent\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * A torrent's files: `hash`. 404 for a torrent qBittorrent doesn't have.
 */
final class GetTorrentFilesRequest extends Request
{
    protected Method $method = Method::GET;

    /**
     * @param  array<string, string>  $filters
     */
    public function __construct(private readonly array $filters = []) {}

    public function resolveEndpoint(): string
    {
        return '/torrents/files';
    }

    /**
     * @return array<string, string>
     */
    protected function defaultQuery(): array
    {
        return $this->filters;
    }
}
