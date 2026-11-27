<?php

namespace App\Services\HostBridge;

use App\Enums\SetupCheckCode;
use App\Exceptions\HostBridgeException;
use App\Services\HostBridge\Requests\CheckVlcCanStartRequest;
use App\Services\HostBridge\Requests\ConvertToWindowsPathsRequest;
use App\Services\HostBridge\Requests\CopyFileRequest;
use App\Services\HostBridge\Requests\LaunchRequest;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * Talks to the host bridge (deploy/host_bridge.py), which starts VLC, converts paths and copies files for a
 * backend that runs in a container. The connector and requests next to it describe the calls; this class sends them and turns
 * failures into a setup check code. The bridge's 422 messages (e.g. VLC not found) stay on the exception for the logs.
 */
class HostBridgeService
{
    /**
     * Checks that VLC can be started.
     *
     * @param  string  $binary  A path, or `vlc` on the PATH
     *
     * @throws HostBridgeException When it can't, or the bridge can't be used
     */
    public function checkVlcCanStart(string $binary): void
    {
        $this->sendRequest(new CheckVlcCanStartRequest($binary), $binary);
    }

    /**
     * Starts VLC with its web interface on localhost.
     *
     * @param  int  $port  The web interface's port
     * @param  string  $password  The web interface's password
     *
     * @throws HostBridgeException
     */
    public function launchVlc(string $binary, int $port, string $password): void
    {
        $this->sendRequest(new LaunchRequest($binary, $port, $password), $binary);
    }

    /**
     * The paths as a Windows VLC opens them, in the same order.
     *
     * @param  list<string>  $paths
     * @return list<string>
     *
     * @throws HostBridgeException
     */
    public function toWindowsPaths(array $paths): array
    {
        $response = $this->sendRequest(new ConvertToWindowsPathsRequest($paths));

        return $response->json('paths');
    }

    /**
     * Copies a file, or links it on one drive, and waits until it's complete.
     *
     * @param  string  $toPath  Must not exist yet
     *
     * @throws HostBridgeException When the copy failed, or the bridge can't be used
     */
    public function copyFile(string $fromPath, string $toPath): void
    {
        $this->sendRequest(new CopyFileRequest($fromPath, $toPath));
    }

    /**
     * Sends a request and turns every way the bridge can fail into its setup check code.
     *
     * @param  string|null  $binary  The VLC binary the request names, for the "not found" and "wrong binary" codes
     *
     * @throws HostBridgeException
     */
    private function sendRequest(Request $request, ?string $binary = null): Response
    {
        $url = config('host_bridge.url');

        try {
            $response = (new HostBridgeConnector)->send($request);
        } catch (FatalRequestException) {
            throw new HostBridgeException(
                "The host bridge at {$url} did not answer. Check that it is running.",
                SetupCheckCode::HostBridgeUnreachable,
                ['url' => $url],
            );
        }

        if ($response->status() === 422) {
            $message = $response->json('message') ?? 'The host bridge rejected the request.';

            // An older bridge sends no code, and only the binary checks have one
            throw match ($response->json('code')) {
                'vlc_not_found' => new HostBridgeException($message, SetupCheckCode::VlcNotFound, ['path' => (string) $binary]),
                'vlc_wrong_binary' => new HostBridgeException($message, SetupCheckCode::VlcWrongBinary, ['path' => (string) $binary]),
                default => new HostBridgeException($message, SetupCheckCode::HostBridgeRejected),
            };
        }

        // An older bridge doesn't know this endpoint yet; an update re-registers the new one
        if ($response->status() === 404) {
            throw new HostBridgeException('The host bridge is out of date. Run moviebrowser3 update.', SetupCheckCode::HostBridgeOutdated);
        }

        if ($response->failed()) {
            throw new HostBridgeException(
                "The host bridge answered {$response->status()}. Check HOST_BRIDGE_TOKEN.",
                SetupCheckCode::HostBridgeUnauthorized,
                ['status' => (string) $response->status()],
            );
        }

        return $response;
    }
}
