<?php

namespace App\Services\QBittorrent;

use App\Dtos\QBittorrent\QBittorrentConnectionData;
use App\Enums\QBittorrentFailure;
use App\Exceptions\QBittorrentUnavailableException;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Talks to qBittorrent, the torrent client that downloads for the app, through its Web API (v2). The client runs on
 * the user's machine, so where it listens and how to log in come with every call.
 */
class QBittorrentService
{
    /**
     * qBittorrent's version, such as `5.1.0`. Also proves the address and login work.
     *
     * @throws QBittorrentUnavailableException When it can't be reached or turns the login away
     */
    public function getVersion(QBittorrentConnectionData $connection): string
    {
        $response = $this->request($connection, 'get', '/api/v2/app/version');

        return ltrim(trim($response->body()), 'v');
    }

    /**
     * Logs in and sends one request with the session cookie the login set.
     *
     * @param  array<string, mixed>  $data
     */
    private function request(QBittorrentConnectionData $connection, string $method, string $path, array $data = []): Response
    {
        $cookies = $this->logIn($connection);

        try {
            $response = $this->client($connection)->withOptions(['cookies' => $cookies])->send(strtoupper($method), $path, $data === [] ? [] : ['form_params' => $data]);
        } catch (ConnectionException) {
            throw new QBittorrentUnavailableException(QBittorrentFailure::Unreachable);
        }

        if (!$response->successful()) {
            $this->logFailure($path, $response);

            throw new QBittorrentUnavailableException(QBittorrentFailure::Unreachable);
        }

        return $response;
    }

    /**
     * Logs in and returns the session cookies. Its name has changed between versions, so the whole jar is kept.
     */
    private function logIn(QBittorrentConnectionData $connection): CookieJar
    {
        $cookies = new CookieJar;

        try {
            $response = $this->client($connection)
                ->withOptions(['cookies' => $cookies])
                ->asForm()
                ->post('/api/v2/auth/login', ['username' => $connection->username, 'password' => $connection->password]);
        } catch (ConnectionException) {
            throw new QBittorrentUnavailableException(QBittorrentFailure::Unreachable);
        }

        // qBittorrent answers a wrong login with 200 "Fails.", and 403 once it has banned the address
        if ($response->status() === 403) {
            throw new QBittorrentUnavailableException(QBittorrentFailure::Banned);
        }

        if ($response->successful() && trim($response->body()) === 'Fails.') {
            throw new QBittorrentUnavailableException(QBittorrentFailure::LoginFailed);
        }

        if (!$response->successful()) {
            $this->logFailure('/api/v2/auth/login', $response);

            throw new QBittorrentUnavailableException(QBittorrentFailure::Unreachable);
        }

        return $cookies;
    }

    private function client(QBittorrentConnectionData $connection): PendingRequest
    {
        return Http::baseUrl(rtrim($connection->url, '/'))->timeout(config('qbittorrent.timeout_seconds'));
    }

    private function logFailure(string $path, Response $response): void
    {
        Log::warning('qBittorrent answered with an error.', [
            'path' => $path,
            'status' => $response->status(),
            'body' => mb_substr($response->body(), 0, 500),
        ]);
    }
}
