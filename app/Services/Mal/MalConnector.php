<?php

namespace App\Services\Mal;

use App\Services\LenientJsonResponse;
use Saloon\Http\Connector;
use Saloon\Traits\Plugins\HasTimeout;

/**
 * MyAnimeList's website: its search box's JSON and its anime pages' HTML. Only `MalService` uses it.
 */
final class MalConnector extends Connector
{
    use HasTimeout;

    protected ?string $response = LenientJsonResponse::class;

    public function resolveBaseUrl(): string
    {
        return rtrim((string) config('mal.base_url'), '/');
    }

    public function getRequestTimeout(): float
    {
        return (float) config('mal.timeout_seconds');
    }

    public function getConnectTimeout(): float
    {
        return $this->getRequestTimeout();
    }
}
