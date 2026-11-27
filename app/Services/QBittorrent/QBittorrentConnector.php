<?php

namespace App\Services\QBittorrent;

use App\Dtos\QBittorrent\QBittorrentConnectionData;
use App\Enums\QBittorrentFailure;
use App\Enums\SettingKey;
use App\Exceptions\DownloadClientNotConfiguredException;
use App\Exceptions\QBittorrentUnavailableException;
use App\Models\Setting\Setting;
use App\Services\QBittorrent\Requests\LogInRequest;
use App\Services\ServiceConnector;
use Illuminate\Support\Str;
use Saloon\Http\PendingRequest;

/**
 * qBittorrent's Web API (v2) on the user's machine. Logs in before every request. Only `QBittorrentService` uses it.
 */
final class QBittorrentConnector extends ServiceConnector
{
    private readonly QBittorrentConnectionData $connection;

    /**
     * @param  QBittorrentConnectionData|null  $connection  Left out, the saved one
     *
     * @throws DownloadClientNotConfiguredException When it's left out while downloads are off
     */
    public function __construct(?QBittorrentConnectionData $connection = null)
    {
        $this->connection = $connection ?? $this->getSavedConnection();
    }

    public function resolveBaseUrl(): string
    {
        return rtrim($this->connection->url, '/').'/api/v2';
    }

    public function getRequestTimeout(): float
    {
        return (float) config('qbittorrent.timeout_seconds');
    }

    /**
     * Saloon calls this before each request.
     *
     * @throws QBittorrentUnavailableException When qBittorrent turns the login away
     */
    public function boot(PendingRequest $pendingRequest): void
    {
        $isLogIn = $pendingRequest->getRequest() instanceof LogInRequest;

        if (!$isLogIn) {
            $pendingRequest->headers()->add('Cookie', $this->logIn());
        }
    }

    /**
     * Returns the session's cookies. Their name has changed between versions, so every cookie the login set is kept;
     * qBittorrent sets none when it lets the app in without a login.
     */
    private function logIn(): string
    {
        $response = $this->send(new LogInRequest($this->connection->username, $this->connection->password));

        // A wrong login is 401 since qBittorrent 5.3, and 200 "Fails." before it; 403 means it has banned the address
        if ($response->status() === 403) {
            throw new QBittorrentUnavailableException(QBittorrentFailure::Banned);
        }

        if ($response->status() === 401 || trim($response->body()) === 'Fails.') {
            throw new QBittorrentUnavailableException(QBittorrentFailure::LoginFailed);
        }

        if ($response->failed()) {
            throw new QBittorrentUnavailableException(QBittorrentFailure::Unreachable);
        }

        $cookies = array_map(
            fn (string $header): string => Str::before($header, ';'),
            (array) $response->header('Set-Cookie'),
        );

        return implode('; ', $cookies);
    }

    private function getSavedConnection(): QBittorrentConnectionData
    {
        $settings = Setting::query()->get()->keyBy('key');
        $url = (string) $settings->get(SettingKey::QBittorrentUrl->value)?->value;
        $downloadPath = (string) $settings->get(SettingKey::DownloadPath->value)?->value;

        // Downloads are off until both are saved
        if ($url === '' || $downloadPath === '') {
            throw new DownloadClientNotConfiguredException;
        }

        return new QBittorrentConnectionData(
            url: $url,
            username: (string) $settings->get(SettingKey::QBittorrentUsername->value)?->value,
            password: (string) $settings->get(SettingKey::QBittorrentPassword->value)?->value,
        );
    }
}
