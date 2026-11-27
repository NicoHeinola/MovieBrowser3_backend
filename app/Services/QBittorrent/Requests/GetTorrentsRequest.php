<?php

namespace App\Services\QBittorrent\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * The torrents with these info hashes.
 */
final class GetTorrentsRequest extends Request
{
    protected Method $method = Method::GET;

    /**
     * @param  list<string>  $hashes
     */
    public function __construct(private readonly array $hashes) {}

    public function resolveEndpoint(): string
    {
        return '/torrents/info';
    }

    /**
     * @return array<string, string>
     */
    protected function defaultQuery(): array
    {
        return ['hashes' => implode('|', $this->hashes)];
    }
}
