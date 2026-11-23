<?php

namespace App\Services\Vlc;

use App\Dtos\EpisodePlayback\VlcConnectionData;
use App\Services\LenientJsonResponse;
use Saloon\Contracts\Authenticator;
use Saloon\Http\Auth\BasicAuthenticator;
use Saloon\Http\Connector;
use Saloon\Traits\Plugins\HasTimeout;

/**
 * The web interface of one VLC the app started, on localhost with its own port and password. Only `VlcService` uses
 * it.
 */
final class VlcConnector extends Connector
{
    use HasTimeout;

    protected ?string $response = LenientJsonResponse::class;

    protected int $connectTimeout = 2;

    protected int $requestTimeout = 2;

    public function __construct(private readonly VlcConnectionData $vlc) {}

    public function resolveBaseUrl(): string
    {
        return "http://127.0.0.1:{$this->vlc->port}";
    }

    protected function defaultAuth(): Authenticator
    {
        return new BasicAuthenticator('', $this->vlc->password);
    }
}
