<?php

namespace App\Services\QBittorrent\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * qBittorrent's version.
 */
final class GetVersionRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/app/version';
    }
}
