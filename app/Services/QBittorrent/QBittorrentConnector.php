<?php

namespace App\Services\QBittorrent;

use App\Dtos\QBittorrent\QBittorrentConnectionData;
use App\Services\LenientJsonResponse;
use Saloon\Http\Connector;
use Saloon\Traits\Plugins\HasTimeout;

/**
 * qBittorrent's Web API (v2) on the user's machine, logged in or not. Only `QBittorrentService` uses it.
 */
final class QBittorrentConnector extends Connector
{
    use HasTimeout;

    protected ?string $response = LenientJsonResponse::class;

    /**
     * @param  string|null  $sessionCookie  The `name=value` cookie a login set, sent with every request
     */
    public function __construct(
        private readonly QBittorrentConnectionData $connection,
        private readonly ?string $sessionCookie = null,
    ) {}

    public function resolveBaseUrl(): string
    {
        return rtrim($this->connection->url, '/').'/api/v2';
    }

    /**
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        return $this->sessionCookie === null ? [] : ['Cookie' => $this->sessionCookie];
    }

    public function getRequestTimeout(): float
    {
        return (float) config('qbittorrent.timeout_seconds');
    }

    public function getConnectTimeout(): float
    {
        return $this->getRequestTimeout();
    }
}
