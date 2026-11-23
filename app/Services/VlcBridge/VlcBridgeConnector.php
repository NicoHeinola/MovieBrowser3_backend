<?php

namespace App\Services\VlcBridge;

use App\Services\LenientJsonResponse;
use Saloon\Contracts\Authenticator;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\HasTimeout;

/**
 * The host-side VLC bridge (deploy/vlc_bridge.py), which a backend in a container asks to start VLC and convert paths.
 * It listens on localhost only and checks a shared token. Only `VlcBridgeService` uses it.
 */
final class VlcBridgeConnector extends Connector
{
    use AcceptsJson;
    use HasTimeout;

    protected ?string $response = LenientJsonResponse::class;

    protected int $connectTimeout = 10;

    protected int $requestTimeout = 10;

    public function resolveBaseUrl(): string
    {
        return rtrim((string) config('vlc.bridge_url'), '/');
    }

    protected function defaultAuth(): Authenticator
    {
        return new TokenAuthenticator((string) config('vlc.bridge_token'));
    }
}
