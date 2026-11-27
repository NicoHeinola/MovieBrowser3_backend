<?php

namespace App\Services;

use Saloon\Http\Connector;
use Saloon\Traits\Plugins\HasTimeout;

/**
 * What every service's connector does: read answers leniently, and wait as long to connect as for an answer.
 */
abstract class ServiceConnector extends Connector
{
    use HasTimeout;

    protected ?string $response = LenientJsonResponse::class;

    public function getConnectTimeout(): float
    {
        return $this->getRequestTimeout();
    }
}
