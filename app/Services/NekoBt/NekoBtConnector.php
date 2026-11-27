<?php

namespace App\Services\NekoBt;

use App\Services\ServiceConnector;
use Saloon\Traits\Plugins\AcceptsJson;

/**
 * nekoBT's public JSON API. Only `NekoBtService` uses it.
 */
final class NekoBtConnector extends ServiceConnector
{
    use AcceptsJson;

    public function resolveBaseUrl(): string
    {
        return rtrim((string) config('nekobt.base_url'), '/').'/api/v1';
    }

    public function getRequestTimeout(): float
    {
        return (float) config('nekobt.timeout_seconds');
    }
}
