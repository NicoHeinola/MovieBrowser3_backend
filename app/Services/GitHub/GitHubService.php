<?php

namespace App\Services\GitHub;

use App\Dtos\AppVersion\ReleaseData;
use App\Services\GitHub\Requests\GetLatestReleaseRequest;
use App\Services\RemembersJson;
use Illuminate\Support\Facades\Log;
use Saloon\Exceptions\SaloonException;

class GitHubService
{
    use RemembersJson;

    /**
     * The newest release of a repo, or null when there's none to be had.
     *
     * @param  string  $repoUrl  Its git URL, https or ssh; other hosts than GitHub have no releases here
     */
    public function getLatestRelease(string $repoUrl): ?ReleaseData
    {
        $repoName = $this->getRepoNameFromUrl($repoUrl);

        if ($repoName === null) {
            Log::warning("Release check skipped: {$repoUrl} is not a GitHub repo");

            return null;
        }

        $request = new GetLatestReleaseRequest($repoName);

        try {
            $release = $this->rememberJson($request, config('releases.cache_seconds'), fn (): array => $this->fetchRelease($request));
        } catch (SaloonException $exception) {
            Log::warning("Release check for {$repoName} failed: {$exception->getMessage()}");

            return null;
        }

        return ReleaseData::fromGitHub($release);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws SaloonException When GitHub doesn't answer, or with an error
     */
    private function fetchRelease(GetLatestReleaseRequest $request): array
    {
        $response = (new GitHubConnector)->send($request);
        $response->throw();

        return $response->json();
    }

    /**
     * @return string|null `owner/name`, from `https://github.com/owner/name(.git)` or `git@github.com:owner/name(.git)`
     */
    private function getRepoNameFromUrl(string $repoUrl): ?string
    {
        if (!preg_match('#github\.com[/:]([\w.-]+/[\w.-]+?)(?:\.git)?/?$#', $repoUrl, $matches)) {
            return null;
        }

        return $matches[1];
    }
}
