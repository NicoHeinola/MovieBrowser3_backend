<?php

namespace App\Services\NekoBt\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * One page of torrents matching a search, with nekoBT's own query names (`NekoBtTorrentMapper::searchQuery`).
 */
final class SearchTorrentsRequest extends Request
{
    protected Method $method = Method::GET;

    /**
     * @param  array<string, string|int>  $search
     */
    public function __construct(private readonly array $search) {}

    public function resolveEndpoint(): string
    {
        return '/torrents/search';
    }

    /**
     * @return array<string, string|int>
     */
    protected function defaultQuery(): array
    {
        return $this->search;
    }
}
