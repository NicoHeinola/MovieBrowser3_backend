<?php

namespace App\Services\QBittorrent\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Torrents with their progress: `hashes` picks them.
 */
final class GetTorrentsRequest extends Request
{
    protected Method $method = Method::GET;

    /**
     * @param  array<string, string>  $filters
     */
    public function __construct(private readonly array $filters = []) {}

    public function resolveEndpoint(): string
    {
        return '/torrents/info';
    }

    /**
     * @return array<string, string>
     */
    protected function defaultQuery(): array
    {
        return $this->filters;
    }
}
