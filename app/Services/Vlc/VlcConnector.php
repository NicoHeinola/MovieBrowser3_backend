<?php

namespace App\Services\Vlc;

use App\Dtos\EpisodePlayback\VlcConnectionData;
use App\Services\ServiceConnector;
use Psr\Http\Message\RequestInterface;
use Saloon\Contracts\Authenticator;
use Saloon\Http\Auth\BasicAuthenticator;
use Saloon\Http\PendingRequest;

/**
 * The web interface of one VLC the app started, on localhost with its own port and password. Only `VlcService` uses
 * it.
 */
final class VlcConnector extends ServiceConnector
{
    protected int $requestTimeout = 2;

    public function __construct(private readonly VlcConnectionData $vlcConnection) {}

    public function resolveBaseUrl(): string
    {
        return "http://127.0.0.1:{$this->vlcConnection->port}";
    }

    protected function defaultAuth(): Authenticator
    {
        return new BasicAuthenticator('', $this->vlcConnection->password);
    }

    /**
     * Sends spaces as `%20`. Saloon writes them as `+`, which VLC reads as a plus sign, so it would look for
     * `Spirited+Away` on the disk. A real plus is `%2B` by now, so no `+` left here is one.
     */
    public function handlePsrRequest(RequestInterface $request, PendingRequest $pendingRequest): RequestInterface
    {
        $uri = $request->getUri();

        return $request->withUri($uri->withQuery(str_replace('+', '%20', $uri->getQuery())));
    }
}
