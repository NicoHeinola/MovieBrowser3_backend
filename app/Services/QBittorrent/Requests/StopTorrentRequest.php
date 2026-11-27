<?php

namespace App\Services\QBittorrent\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasFormBody;

/**
 * Stops a torrent.
 */
final class StopTorrentRequest extends Request implements HasBody
{
    use HasFormBody;

    protected Method $method = Method::POST;

    public function __construct(private readonly string $hash) {}

    public function resolveEndpoint(): string
    {
        return '/torrents/stop';
    }

    /**
     * @return array<string, string>
     */
    protected function defaultBody(): array
    {
        return ['hashes' => $this->hash];
    }
}
