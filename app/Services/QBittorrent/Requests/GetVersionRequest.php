<?php

namespace App\Services\QBittorrent\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * qBittorrent's version, such as `v5.1.0`, as plain text.
 */
final class GetVersionRequest extends Request
{
    protected Method $method = Method::GET;

    /**
     * @param  array<string, string>  $filters
     */
    public function __construct(private readonly array $filters = []) {}

    public function resolveEndpoint(): string
    {
        return '/app/version';
    }

    /**
     * @return array<string, string>
     */
    protected function defaultQuery(): array
    {
        return $this->filters;
    }
}
