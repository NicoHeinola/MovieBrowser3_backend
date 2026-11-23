<?php

namespace App\Services\GitHub;

use App\Services\LenientJsonResponse;
use Saloon\Http\Connector;
use Saloon\Traits\Plugins\HasTimeout;

/**
 * GitHub's REST API, without a token. Only `GitHubService` uses it.
 */
final class GitHubConnector extends Connector
{
    use HasTimeout;

    protected ?string $response = LenientJsonResponse::class;

    public function resolveBaseUrl(): string
    {
        return rtrim((string) config('github.base_url'), '/');
    }

    /**
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        return ['Accept' => 'application/vnd.github+json'];
    }

    public function getRequestTimeout(): float
    {
        return (float) config('github.timeout_seconds');
    }

    public function getConnectTimeout(): float
    {
        return $this->getRequestTimeout();
    }
}
