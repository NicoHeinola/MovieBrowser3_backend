<?php

namespace App\Actions\AppVersion;

use App\Dtos\AppVersion\LatestReleaseData;
use App\Dtos\AppVersion\ReleaseData;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class FetchLatestReleaseAction
{
    use AsAction;

    /**
     * The newest GitHub release of a repo, by its git URL (https or ssh), cached. A failed check is cached too, for a
     * shorter time, so an offline install doesn't call GitHub on every request.
     */
    public function handle(string $repoUrl): LatestReleaseData
    {
        $key = "releases.latest.{$repoUrl}";

        // Cached as an array, never a bare null: Cache::get can't tell a cached failure from a miss
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return LatestReleaseData::from($cached);
        }

        $latest = new LatestReleaseData($this->request($repoUrl), CarbonImmutable::now());

        $seconds = $latest->release !== null ? config('releases.cache_seconds') : config('releases.failure_cache_seconds');
        Cache::put($key, $latest->toArray(), $seconds);

        return $latest;
    }

    private function request(string $repoUrl): ?ReleaseData
    {
        // https://github.com/owner/name(.git) or git@github.com:owner/name(.git); other hosts have no releases API here
        if (!preg_match('#github\.com[/:]([\w.-]+)/([\w.-]+?)(?:\.git)?/?$#', $repoUrl, $matches)) {
            Log::warning("Release check skipped: {$repoUrl} is not a GitHub repo");

            return null;
        }

        $repo = "{$matches[1]}/{$matches[2]}";

        try {
            $response = Http::withHeaders(['Accept' => 'application/vnd.github+json'])
                ->timeout(5)
                ->get("https://api.github.com/repos/{$repo}/releases/latest");
        } catch (ConnectionException $exception) {
            Log::warning("Release check for {$repo} failed: {$exception->getMessage()}");

            return null;
        }

        if ($response->failed()) {
            Log::warning("Release check for {$repo} failed: GitHub answered {$response->status()}");

            return null;
        }

        // Tags are vX.Y.Z; anything else (another tag name, a missing field) isn't a version to compare
        $version = ltrim((string) $response->json('tag_name'), 'v');
        $url = $response->json('html_url');

        if (!preg_match('/^\d+\.\d+\.\d+$/', $version) || !is_string($url)) {
            Log::warning("Release check for {$repo} found no vX.Y.Z release tag");

            return null;
        }

        return new ReleaseData($version, $url);
    }
}
