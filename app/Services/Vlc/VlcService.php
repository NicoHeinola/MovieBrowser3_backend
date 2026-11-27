<?php

namespace App\Services\Vlc;

use App\Dtos\EpisodePlayback\VlcConnectionData;
use App\Dtos\EpisodePlayback\VlcStatusData;
use App\Services\Vlc\Requests\EnqueueRequest;
use App\Services\Vlc\Requests\GetStatusRequest;
use App\Services\Vlc\Requests\PlayRequest;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * One VLC window the app started, through its web interface. Each has its own port and password.
 */
class VlcService
{
    public function __construct(private readonly VlcConnectionData $vlcConnection) {}

    /**
     * What VLC is doing, or null when it doesn't answer: it hasn't started yet or was closed.
     */
    public function getStatus(): ?VlcStatusData
    {
        $response = $this->sendRequest(new GetStatusRequest);

        if ($response === null) {
            return null;
        }

        return VlcStatusData::from($response->json());
    }

    /**
     * Adds a file to the end of the playlist.
     *
     * @return bool Whether VLC answered
     */
    public function enqueueFile(string $path): bool
    {
        return $this->sendRequest(new EnqueueRequest($path)) !== null;
    }

    /**
     * Adds a file to the end of the playlist and plays it.
     *
     * @param  int  $startSeconds  How far into the file it starts
     * @return bool Whether VLC answered
     */
    public function playFile(string $path, int $startSeconds): bool
    {
        return $this->sendRequest(new PlayRequest($path, $startSeconds)) !== null;
    }

    private function sendRequest(Request $request): ?Response
    {
        $connector = new VlcConnector($this->vlcConnection);

        try {
            $response = $connector->send($request);
        } catch (FatalRequestException) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        return $response;
    }
}
