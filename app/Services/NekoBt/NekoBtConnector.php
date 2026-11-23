<?php

namespace App\Services\NekoBt;

use App\Services\LenientJsonResponse;
use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\HasTimeout;

/**
 * nekoBT's public JSON API. Only `NekoBtService` uses it.
 */
final class NekoBtConnector extends Connector
{
    use AcceptsJson;
    use HasTimeout;

    protected ?string $response = LenientJsonResponse::class;

    public function resolveBaseUrl(): string
    {
        return rtrim((string) config('nekobt.base_url'), '/').'/api/v1';
    }

    public function getRequestTimeout(): float
    {
        return (float) config('nekobt.timeout_seconds');
    }

    public function getConnectTimeout(): float
    {
        return $this->getRequestTimeout();
    }
}
