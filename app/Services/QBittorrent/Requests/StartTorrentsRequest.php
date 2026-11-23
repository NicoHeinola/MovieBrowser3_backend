<?php

namespace App\Services\QBittorrent\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasFormBody;

/**
 * Starts torrents: `hashes`.
 */
final class StartTorrentsRequest extends Request implements HasBody
{
    use HasFormBody;

    protected Method $method = Method::POST;

    /**
     * @param  array<string, string>  $fields
     */
    public function __construct(private readonly array $fields) {}

    public function resolveEndpoint(): string
    {
        return '/torrents/start';
    }

    /**
     * @return array<string, string>
     */
    protected function defaultBody(): array
    {
        return $this->fields;
    }
}
