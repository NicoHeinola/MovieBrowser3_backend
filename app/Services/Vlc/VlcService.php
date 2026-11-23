<?php

namespace App\Services\Vlc;

use App\Dtos\EpisodePlayback\VlcConnectionData;
use App\Services\Vlc\Requests\GetStatusRequest;
use Saloon\Exceptions\Request\FatalRequestException;

/**
 * Talks to VLC's web interface, of a VLC the app started. The connector and request next to it describe the call.
 */
class VlcService
{
    /**
     * VLC's status (current file, state, position), after running `$command` when one is given. Null when VLC doesn't
     * answer, which means it hasn't started yet or was closed.
     *
     * @param  array<string, string>  $command
     * @return array<string, mixed>|null
     */
    public function getStatus(VlcConnectionData $vlc, array $command = []): ?array
    {
        try {
            $response = (new VlcConnector($vlc))->send(new GetStatusRequest($command));
        } catch (FatalRequestException) {
            return null;
        }

        return $response->successful() ? $response->json() : null;
    }
}
