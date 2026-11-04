<?php

namespace App\Actions\EpisodePlayback;

use App\Enums\SetupCheckCode;
use App\Exceptions\VlcBridgeException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

class RequestVlcBridgeAction
{
    use AsAction;

    /**
     * Calls the host-side VLC bridge (deploy/vlc_bridge.py), which starts VLC and converts paths for a backend that
     * runs in a container. Its 422 messages (e.g. VLC not found) stay on the exception for the logs, next to a setup check code.
     *
     * @param  string  $endpoint  `launch` or `windows-paths`
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed> The decoded response body, empty for a 204.
     *
     * @throws VlcBridgeException
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
            throw new VlcBridgeException(
                "The VLC bridge at {$url} did not answer. Check that it is running.",
                SetupCheckCode::VlcBridgeUnreachable,
                ['url' => $url],
            );
        }

        if ($response->status() === 422) {
            $message = $response->json('message') ?? 'The VLC bridge rejected the request.';

            // An older bridge sends no code, and only the binary checks have one
            throw match ($response->json('code')) {
                'vlc_not_found' => new VlcBridgeException($message, SetupCheckCode::VlcNotFound, ['path' => $payload['binary']]),
                'vlc_wrong_binary' => new VlcBridgeException($message, SetupCheckCode::VlcWrongBinary, ['path' => $payload['binary']]),
                default => new VlcBridgeException($message, SetupCheckCode::VlcBridgeRejected),
            };
        }

        // An older bridge doesn't know this endpoint yet; an update re-registers the new one
        if ($response->status() === 404) {
            throw new VlcBridgeException('The VLC bridge is out of date. Run moviebrowser3 update.', SetupCheckCode::VlcBridgeOutdated);
        }

        if ($response->failed()) {
            throw new VlcBridgeException(
                "The VLC bridge answered {$response->status()}. Check VLC_BRIDGE_TOKEN.",
                SetupCheckCode::VlcBridgeUnauthorized,
                ['status' => (string) $response->status()],
            );
        }

        return $response->json() ?? [];
    }
}
