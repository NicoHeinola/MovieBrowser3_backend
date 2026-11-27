<?php

namespace App\Services\QBittorrent;

use App\Dtos\QBittorrent\QBittorrentConnectionData;
use App\Dtos\QBittorrent\QBittorrentFileData;
use App\Dtos\QBittorrent\QBittorrentNewTorrentData;
use App\Dtos\QBittorrent\QBittorrentTorrentData;
use App\Enums\QBittorrentFailure;
use App\Exceptions\DownloadClientNotConfiguredException;
use App\Exceptions\QBittorrentUnavailableException;
use App\Services\QBittorrent\Requests\AddTorrentRequest;
use App\Services\QBittorrent\Requests\DeleteTorrentRequest;
use App\Services\QBittorrent\Requests\GetTorrentFilesRequest;
use App\Services\QBittorrent\Requests\GetTorrentsRequest;
use App\Services\QBittorrent\Requests\GetVersionRequest;
use App\Services\QBittorrent\Requests\RecheckTorrentRequest;
use App\Services\QBittorrent\Requests\SetFilePriorityRequest;
use App\Services\QBittorrent\Requests\StartTorrentRequest;
use App\Services\QBittorrent\Requests\StopTorrentRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * Talks to qBittorrent, the torrent client that downloads for the app, through its Web API (v2). It reaches the qBittorrent
 * saved in the settings and logs in on first use, once per request or job (it is bound `scoped`). The connector and
 * requests next to it describe the calls.
 */
class QBittorrentService
{
    /**
     * Logs in with values that aren't saved yet, such as the setup wizard's, and returns that qBittorrent's version, such
     * as `5.1.0`.
     *
     * @throws QBittorrentUnavailableException When it can't be reached or turns the login away
     */
    public function testConnection(QBittorrentConnectionData $connection): string
    {
        $response = $this->sendRequestWith(new QBittorrentConnector($connection), new GetVersionRequest);

        return ltrim(trim($response->body()), 'v');
    }

    /**
     * Asks for a torrent to be added, without downloading any of it yet. What qBittorrent answers says little: it turns
     * down a torrent it already has, and takes one it can't read. Whether it has the torrent afterwards is what counts.
     */
    public function addTorrent(QBittorrentNewTorrentData $torrent): void
    {
        $this->sendRequest(new AddTorrentRequest($torrent));
    }

    /**
     * The torrent with this info hash, or null when qBittorrent doesn't have it.
     */
    public function getTorrent(string $hash): ?QBittorrentTorrentData
    {
        $torrents = $this->getTorrents([$hash]);

        return $torrents[$hash] ?? null;
    }

    /**
     * The torrents with these info hashes that qBittorrent has, by hash. One request for a whole list of downloads.
     *
     * @param  list<string>  $hashes
     * @return array<string, QBittorrentTorrentData>
     */
    public function getTorrents(array $hashes): array
    {
        // Without hashes qBittorrent would answer with every torrent it has
        if ($hashes === []) {
            return [];
        }

        $response = $this->sendRequest(new GetTorrentsRequest($hashes));
        $torrents = QBittorrentTorrentData::collect($response->json(), Collection::class);

        return $torrents->keyBy('hash')->all();
    }

    /**
     * The torrent's files, empty while a magnet's file list is still being fetched, or null when qBittorrent doesn't
     * have the torrent.
     *
     * @return list<QBittorrentFileData>|null
     */
    public function getTorrentFiles(string $hash): ?array
    {
        $response = $this->sendRequest(new GetTorrentFilesRequest($hash));

        if ($response->status() === 404) {
            return null;
        }

        return QBittorrentFileData::collect($response->json());
    }

    /**
     * @param  list<int>  $fileIndexes
     */
    public function downloadFiles(string $hash, array $fileIndexes): void
    {
        $this->setFilePriority($hash, $fileIndexes, 1);
    }

    /**
     * @param  list<int>  $fileIndexes
     */
    public function skipFiles(string $hash, array $fileIndexes): void
    {
        $this->setFilePriority($hash, $fileIndexes, 0);
    }

    public function startTorrent(string $hash): void
    {
        $this->sendRequest(new StartTorrentRequest($hash));
    }

    public function stopTorrent(string $hash): void
    {
        $this->sendRequest(new StopTorrentRequest($hash));
    }

    /**
     * Has qBittorrent check the torrent's files on the disk, and download again what isn't there.
     */
    public function recheckTorrent(string $hash): void
    {
        $this->sendRequest(new RecheckTorrentRequest($hash));
    }

    /**
     * Removes the torrent and whatever it downloaded. Does nothing when qBittorrent doesn't have it.
     */
    public function deleteTorrent(string $hash): void
    {
        $this->sendRequest(new DeleteTorrentRequest($hash));
    }

    /**
     * @param  list<int>  $fileIndexes
     */
    private function setFilePriority(string $hash, array $fileIndexes, int $priority): void
    {
        // qBittorrent turns away a request that names no file
        if ($fileIndexes !== []) {
            $this->sendRequest(new SetFilePriorityRequest($hash, $fileIndexes, $priority));
        }
    }

    /**
     * @throws DownloadClientNotConfiguredException While downloads are off
     * @throws QBittorrentUnavailableException
     */
    private function sendRequest(Request $request): Response
    {
        return $this->sendRequestWith(new QBittorrentConnector, $request);
    }

    /**
     * Sends a request. 404 is an answer (an unknown torrent); any other error isn't.
     *
     * @throws QBittorrentUnavailableException
     */
    private function sendRequestWith(QBittorrentConnector $connector, Request $request): Response
    {
        try {
            $response = $connector->send($request);
        } catch (FatalRequestException) {
            throw new QBittorrentUnavailableException(QBittorrentFailure::Unreachable);
        }

        if ($response->failed() && $response->status() !== 404) {
            $body = mb_substr($response->body(), 0, 500);
            Log::warning("qBittorrent answered {$response->status()} to {$request->resolveEndpoint()}: {$body}");

            throw new QBittorrentUnavailableException(QBittorrentFailure::Unreachable);
        }

        return $response;
    }
}
