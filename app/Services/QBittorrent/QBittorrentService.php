<?php

namespace App\Services\QBittorrent;

use App\Dtos\QBittorrent\QBittorrentConnectionData;
use App\Dtos\QBittorrent\QBittorrentFileData;
use App\Dtos\QBittorrent\QBittorrentNewTorrentData;
use App\Dtos\QBittorrent\QBittorrentTorrentData;
use App\Enums\QBittorrentFailure;
use App\Exceptions\QBittorrentUnavailableException;
use App\Services\QBittorrent\Requests\AddTorrentRequest;
use App\Services\QBittorrent\Requests\DeleteTorrentsRequest;
use App\Services\QBittorrent\Requests\GetTorrentFilesRequest;
use App\Services\QBittorrent\Requests\GetTorrentsRequest;
use App\Services\QBittorrent\Requests\GetVersionRequest;
use App\Services\QBittorrent\Requests\LogInRequest;
use App\Services\QBittorrent\Requests\SetFilePriorityRequest;
use App\Services\QBittorrent\Requests\StartTorrentsRequest;
use App\Services\QBittorrent\Requests\StopTorrentsRequest;
use Closure;
use Illuminate\Support\Facades\Log;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * Talks to qBittorrent, the torrent client that downloads for the app, through its Web API (v2). It reaches the
 * qBittorrent saved in the settings, read on first use, and logs in once per request or job. The connector and
 * requests next to it describe the calls and the mapper translates the answers; this class logs in, asks and checks
 * the answers.
 */
class QBittorrentService
{
    /**
     * Session cookies by login, so a run of calls logs in once. The service is scoped to the request or job, so its
     * actions share them. The password is part of the key, so checking a changed password logs in again.
     *
     * @var array<string, string>
     */
    private array $sessions = [];

    /** The saved connection, once read */
    private ?QBittorrentConnectionData $connection = null;

    /**
     * @param  Closure(): QBittorrentConnectionData  $resolveConnection  Reads the saved connection; it throws while
     *                                                                   downloads are off. `AppServiceProvider` wires it.
     */
    public function __construct(
        private readonly QBittorrentMapper $mapper,
        private readonly Closure $resolveConnection,
    ) {}

    /**
     * Logs in with values that aren't saved yet, such as the setup wizard's, and returns that qBittorrent's version.
     * The saved connection and its session stay as they are.
     *
     * @throws QBittorrentUnavailableException When it can't be reached or turns the login away
     */
    public function testConnection(QBittorrentConnectionData $values): string
    {
        $response = $this->sendWith($values, $this->logIn($values), new GetVersionRequest);

        if ($response->failed()) {
            $this->logFailure(new GetVersionRequest, $response);

            throw new QBittorrentUnavailableException(QBittorrentFailure::Unreachable);
        }

        return ltrim(trim($response->body()), 'v');
    }

    /**
     * qBittorrent's version, such as `5.1.0`. Also proves the address and login work.
     *
     * @throws QBittorrentUnavailableException When it can't be reached or turns the login away
     */
    public function getVersion(): string
    {
        return ltrim(trim($this->send(new GetVersionRequest)->body()), 'v');
    }

    /**
     * Adds a torrent without downloading any of it yet. A `.torrent` link is added stopped; a magnet runs only until
     * qBittorrent has fetched its file list, then stops.
     *
     * @return bool False when qBittorrent turned it down, which it also does for a torrent it already has
     */
    public function addTorrent(QBittorrentNewTorrentData $torrent): bool
    {
        $isMagnet = str_starts_with($torrent->url, 'magnet:');

        $response = $this->send(new AddTorrentRequest([
            'urls' => $torrent->url,
            'savepath' => $torrent->savePath,
            'category' => $torrent->category,
            'stopped' => $isMagnet ? 'false' : 'true',
            'stopCondition' => $isMagnet ? 'MetadataReceived' : 'None',
        ]));

        return trim($response->body()) !== 'Fails.';
    }

    /**
     * The torrent with this info hash, or null when qBittorrent doesn't have it.
     */
    public function getTorrent(string $hash): ?QBittorrentTorrentData
    {
        $torrents = $this->send(new GetTorrentsRequest(['hashes' => $hash]))->json();

        return is_array($torrents) && isset($torrents[0]['hash']) ? $this->mapper->torrent($torrents[0]) : null;
    }

    /**
     * The torrent's files, empty while a magnet's file list is still being fetched, or null when qBittorrent doesn't
     * have the torrent.
     *
     * @return list<QBittorrentFileData>|null
     */
    public function getFiles(string $hash): ?array
    {
        $response = $this->send(new GetTorrentFilesRequest(['hash' => $hash]), allowNotFound: true);

        if ($response->status() === 404) {
            return null;
        }

        return $this->mapper->files(is_array($response->json()) ? $response->json() : []);
    }

    /**
     * Sets the priority of some files: 0 skips them, 1 downloads them.
     *
     * @param  list<int>  $indexes
     */
    public function setFilePriority(string $hash, array $indexes, int $priority): void
    {
        if ($indexes === []) {
            return;
        }

        $this->send(new SetFilePriorityRequest([
            'hash' => $hash,
            'id' => implode('|', $indexes),
            'priority' => (string) $priority,
        ]));
    }

    public function start(string $hash): void
    {
        $this->send(new StartTorrentsRequest(['hashes' => $hash]));
    }

    public function stop(string $hash): void
    {
        $this->send(new StopTorrentsRequest(['hashes' => $hash]));
    }

    /**
     * Removes the torrent, and with `$deleteFiles` whatever it downloaded.
     */
    public function deleteTorrent(string $hash, bool $deleteFiles): void
    {
        $this->send(new DeleteTorrentsRequest([
            'hashes' => $hash,
            'deleteFiles' => $deleteFiles ? 'true' : 'false',
        ]));
    }

    /**
     * Sends one request in a logged-in session. A session qBittorrent has forgotten (403) is logged in again once.
     *
     * @param  bool  $allowNotFound  Whether a 404 is an answer (an unknown torrent) rather than a failure
     */
    private function send(Request $request, bool $allowNotFound = false): Response
    {
        $connection = $this->connection();
        $response = $this->sendWith($connection, $this->session($connection), $request);

        if ($response->status() === 403) {
            $response = $this->sendWith($connection, $this->session($connection, fresh: true), $request);
        }

        if ($response->failed() && !($allowNotFound && $response->status() === 404)) {
            $this->logFailure($request, $response);

            throw new QBittorrentUnavailableException(QBittorrentFailure::Unreachable);
        }

        return $response;
    }

    /**
     * The qBittorrent to talk to, reading the saved one the first time it's needed.
     */
    private function connection(): QBittorrentConnectionData
    {
        return $this->connection ??= ($this->resolveConnection)();
    }

    private function sendWith(QBittorrentConnectionData $connection, ?string $sessionCookie, Request $request): Response
    {
        try {
            return (new QBittorrentConnector($connection, $sessionCookie))->send($request);
        } catch (FatalRequestException) {
            throw new QBittorrentUnavailableException(QBittorrentFailure::Unreachable);
        }
    }

    /**
     * The session cookie for this login, logging in when there is none yet (or `$fresh` asks for a new one).
     */
    private function session(QBittorrentConnectionData $connection, bool $fresh = false): ?string
    {
        $key = hash('sha256', "{$connection->url}|{$connection->username}|{$connection->password}");

        if ($fresh || !array_key_exists($key, $this->sessions)) {
            $this->sessions[$key] = $this->logIn($connection);
        }

        return $this->sessions[$key];
    }

    /**
     * Logs in and returns the session cookie as `name=value`, or null when qBittorrent let the app in without one
     * (its "bypass authentication for localhost" setting). The cookie's name has changed between versions, so every
     * cookie the login set is kept.
     */
    private function logIn(QBittorrentConnectionData $connection): ?string
    {
        $response = $this->sendWith($connection, null, new LogInRequest([
            'username' => $connection->username,
            'password' => $connection->password,
        ]));

        // A wrong login is 401 since qBittorrent 5.3, and 200 "Fails." before it; 403 means it has banned the address
        if ($response->status() === 403) {
            throw new QBittorrentUnavailableException(QBittorrentFailure::Banned);
        }

        if ($response->status() === 401 || ($response->successful() && trim($response->body()) === 'Fails.')) {
            throw new QBittorrentUnavailableException(QBittorrentFailure::LoginFailed);
        }

        if ($response->failed()) {
            $this->logFailure(new LogInRequest([]), $response);

            throw new QBittorrentUnavailableException(QBittorrentFailure::Unreachable);
        }

        $cookies = array_map(
            fn (string $header): string => trim(explode(';', $header, 2)[0]),
            (array) ($response->header('Set-Cookie') ?? []),
        );

        return $cookies === [] ? null : implode('; ', $cookies);
    }

    private function logFailure(Request $request, Response $response): void
    {
        Log::warning('qBittorrent answered with an error.', [
            'endpoint' => $request->resolveEndpoint(),
            'status' => $response->status(),
            'body' => mb_substr($response->body(), 0, 500),
        ]);
    }
}
