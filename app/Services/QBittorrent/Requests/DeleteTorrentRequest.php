<?php

namespace App\Services\QBittorrent\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasFormBody;

/**
 * Removes a torrent and whatever it downloaded.
 */
final class DeleteTorrentRequest extends Request implements HasBody
{
    use HasFormBody;

    protected Method $method = Method::POST;

    public function __construct(private readonly string $hash) {}

    public function resolveEndpoint(): string
    {
        return '/torrents/delete';
    }

    /**
     * @return array<string, string>
     */
    protected function defaultBody(): array
    {
        return ['hashes' => $this->hash, 'deleteFiles' => 'true'];
    }
}
