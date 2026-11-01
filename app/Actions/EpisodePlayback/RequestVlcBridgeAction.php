<?php

namespace App\Actions\EpisodePlayback;

use App\Exceptions\VlcLaunchException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

class RequestVlcBridgeAction
{
    use AsAction;

    /**
     * Calls the host-side VLC bridge (deploy/vlc_bridge.py), which starts VLC and converts paths for a backend that
     * runs in a container. Its 422 messages (e.g. VLC not found) are passed on as they are.
     *
     * @param  string  $endpoint  `launch` or `windows-paths`
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed> The decoded response body, empty for a 204.
     */
    public function handle(string $endpoint, array $payload): array
    {
        $url = config('vlc.bridge_url');

        try {
            $response = Http::withToken(config('vlc.bridge_token'))
                ->acceptJson()
                ->timeout(10)
                ->post("{$url}/{$endpoint}", $payload);
        } catch (ConnectionException) {
            throw new VlcLaunchException("The VLC bridge at {$url} did not answer. Check that it is running.");
        }

        if ($response->status() === 422) {
            throw new VlcLaunchException($response->json('message') ?? 'The VLC bridge rejected the request.');
        }

        // An older bridge doesn't know this endpoint yet; an update re-registers the new one
        if ($response->status() === 404) {
            throw new VlcLaunchException('The VLC bridge is out of date. Run moviebrowser3 update.');
        }

        if ($response->failed()) {
            throw new VlcLaunchException("The VLC bridge answered {$response->status()}. Check VLC_BRIDGE_TOKEN.");
        }

        return $response->json() ?? [];
    }
}
