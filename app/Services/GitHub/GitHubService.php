<?php

namespace App\Services\GitHub;

use App\Dtos\AppVersion\ReleaseData;
use App\Exceptions\GitHubUnavailableException;
use App\Services\GitHub\Requests\GetLatestReleaseRequest;
use Illuminate\Support\Facades\Log;
use Saloon\Exceptions\Request\FatalRequestException;

/**
 * Talks to GitHub: it reads a repo's newest release, for the update notice. The connector and request next to it
 * describe the call; this class asks and checks the answer.
 */
class GitHubService
{
    /**
     * The newest release of a repo, as its `X.Y.Z` version and page.
     *
     * @param  string  $repo  `owner/name`
     *
     * @throws GitHubUnavailableException When GitHub doesn't answer, or its newest release has no vX.Y.Z tag
     */
    public function getLatestRelease(string $repo): ReleaseData
    {
        try {
            $response = (new GitHubConnector)->send(new GetLatestReleaseRequest($repo));
        } catch (FatalRequestException $exception) {
            Log::warning("Release check for {$repo} failed: {$exception->getMessage()}");

            throw new GitHubUnavailableException;
        }

        if ($response->failed()) {
            Log::warning("Release check for {$repo} failed: GitHub answered {$response->status()}");

            throw new GitHubUnavailableException;
        }

        // Tags are vX.Y.Z; anything else (another tag name, a missing field) isn't a version to compare
        $version = ltrim((string) $response->json('tag_name'), 'v');
        $url = $response->json('html_url');

        if (!preg_match('/^\d+\.\d+\.\d+$/', $version) || !is_string($url)) {
            Log::warning("Release check for {$repo} found no vX.Y.Z release tag");

            throw new GitHubUnavailableException;
        }

        return new ReleaseData($version, $url);
    }
}
