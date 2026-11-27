<?php

namespace App\Services\HostBridge;

use App\Services\ServiceConnector;
use Saloon\Contracts\Authenticator;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Traits\Plugins\AcceptsJson;

/**
 * The host bridge (deploy/host_bridge.py), which a backend in a container asks to start VLC, convert paths and copy files.
 * It listens on localhost only and checks a shared token. Only `HostBridgeService` uses it.
 */
final class HostBridgeConnector extends ServiceConnector
{
    use AcceptsJson;

    protected int $requestTimeout = 10;

    public function resolveBaseUrl(): string
    {
        return rtrim((string) config('host_bridge.url'), '/');
    }

    protected function defaultAuth(): Authenticator
    {
        return new TokenAuthenticator((string) config('host_bridge.token'));
    }
}
