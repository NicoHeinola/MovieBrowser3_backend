<?php

namespace App\Services\VlcBridge;

use App\Enums\SetupCheckCode;
use App\Exceptions\VlcBridgeException;
use App\Services\VlcBridge\Requests\CheckBinaryRequest;
use App\Services\VlcBridge\Requests\ConvertToWindowsPathsRequest;
use App\Services\VlcBridge\Requests\LaunchRequest;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * Talks to the host-side VLC bridge (deploy/vlc_bridge.py), which starts VLC and converts paths for a backend that
 * runs in a container. The connector and requests next to it describe the calls; this class sends them and turns
 * failures into a setup check code. The bridge's 422 messages (e.g. VLC not found) stay on the exception for the logs.
 */
class VlcBridgeService
{
    /**
     * Checks that VLC can be started from `$binary`, a path or `vlc` on the PATH.
     *
     * @throws VlcBridgeException When it can't, or the bridge can't be used
     */
    public function checkBinary(string $binary): void
    {
        $this->send(new CheckBinaryRequest(['binary' => $binary]), $binary);
    }

    /**
     * Starts VLC with its web interface on localhost, on `$port` with `$password`.
     *
     * @throws VlcBridgeException
     */
    public function launch(string $binary, int $port, string $password): void
    {
        $this->send(new LaunchRequest(['binary' => $binary, 'port' => $port, 'password' => $password]), $binary);
    }

    /**
     * The paths as a Windows VLC opens them, in the same order.
     *
     * @param  list<string>  $paths
     * @return list<string>
     *
     * @throws VlcBridgeException
     */
    public function toWindowsPaths(array $paths): array
    {
        return $this->send(new ConvertToWindowsPathsRequest(['paths' => $paths]))->json('paths');
    }

    /**
     * Sends a request and turns every way the bridge can fail into its setup check code.
     *
     * @param  string|null  $binary  The VLC binary the request names, for the "not found" and "wrong binary" codes
     *
     * @throws VlcBridgeException
     */
    private function send(Request $request, ?string $binary = null): Response
    {
        $url = config('vlc.bridge_url');

        try {
            $response = (new VlcBridgeConnector)->send($request);
        } catch (FatalRequestException) {
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
                'vlc_not_found' => new VlcBridgeException($message, SetupCheckCode::VlcNotFound, ['path' => (string) $binary]),
                'vlc_wrong_binary' => new VlcBridgeException($message, SetupCheckCode::VlcWrongBinary, ['path' => (string) $binary]),
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

        return $response;
    }
}
