<?php

namespace App\Services\NekoBt;

use App\Dtos\NekoBt\NekoBtSearchData;
use App\Dtos\NekoBt\NekoBtTorrentPageData;
use App\Dtos\ShowTemplate\ShowTemplateData;
use App\Exceptions\ShowTemplateNotFoundException;
use App\Exceptions\ShowTemplateUnavailableException;
use App\Exceptions\TorrentSiteUnavailableException;
use App\Services\ShowTemplateSource;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Talks to nekoBT, an anime torrent tracker, through its public JSON API: it searches its torrents, and describes the
 * show behind one of its media pages (`/media/s1453` for a series, `/media/m134` for a movie) as a show template.
 * The mappers next to it translate nekoBT's answers; this class only asks and checks the answers.
 */
class NekoBtService implements ShowTemplateSource
{
    /** Results per page: the API's own default, and few enough rows to show at once */
    public const PER_PAGE = 50;

    public function __construct(
        private readonly NekoBtTorrentMapper $torrents,
        private readonly NekoBtShowTemplateMapper $showTemplates,
    ) {}

    /**
     * One page of torrents matching the search.
     *
     * @throws TorrentSiteUnavailableException When nekoBT can't be reached or answers with an error
     */
    public function getTorrents(NekoBtSearchData $search): NekoBtTorrentPageData
    {
        $response = $this->request('/api/v1/torrents/search', $this->torrents->searchQuery($search, self::PER_PAGE));

        if ($response === null || !$this->isAnswer($response) || !is_array($response->json('data.results'))) {
            $this->logFailure($response);

            throw new TorrentSiteUnavailableException('nekobt');
        }

        return $this->torrents->page($response->json('data'), $search, self::PER_PAGE);
    }

    public function supportsShowTemplateUrl(string $url): bool
    {
        return $this->mediaId($url) !== null;
    }

    public function getShowTemplate(string $url): ShowTemplateData
    {
        $mediaId = $this->mediaId($url) ?? throw new ShowTemplateNotFoundException;
        $response = $this->request("/api/v1/media/{$mediaId}");

        // A mistyped or removed id is the user's to fix, not nekoBT being down
        if ($response?->status() === 404) {
            throw new ShowTemplateNotFoundException;
        }

        if ($response === null || !$this->isAnswer($response) || !is_array($response->json('data'))) {
            $this->logFailure($response);

            throw new ShowTemplateUnavailableException;
        }

        return $this->showTemplates->showTemplate($url, $response->json('data'));
    }

    /**
     * The media id in a link to a nekoBT media page, such as `s1453` in `https://nekobt.to/media/s1453`: `s` for a
     * series, `m` for a movie.
     */
    private function mediaId(string $url): ?string
    {
        $host = preg_quote((string) parse_url(config('nekobt.base_url'), PHP_URL_HOST), '#');

        return preg_match("#^https?://(?:www\.)?{$host}/media/([sm]\d+)/?(?:[?\#].*)?$#i", trim($url), $matches)
            ? strtolower($matches[1])
            : null;
    }

    /**
     * Sends a GET to the API.
     *
     * @param  array<string, string|int>  $query
     * @return Response|null The answer, or null when nekoBT didn't answer (logged)
     */
    private function request(string $path, array $query = []): ?Response
    {
        try {
            return Http::acceptJson()->timeout(config('nekobt.timeout_seconds'))->get(config('nekobt.base_url').$path, $query);
        } catch (ConnectionException $exception) {
            Log::warning("nekoBT did not answer: {$exception->getMessage()}");

            return null;
        }
    }

    /**
     * Whether a response is a successful API answer. The API wraps every answer in `{ error, data }` and reports
     * failures as `{ error: true, message }`.
     */
    private function isAnswer(Response $response): bool
    {
        return $response->successful() && $response->json('error') === false;
    }

    /**
     * Logs what nekoBT answered instead of a usable answer; a missing answer was logged when it happened.
     */
    private function logFailure(?Response $response): void
    {
        if ($response !== null) {
            $message = (string) ($response->json('message') ?? mb_substr($response->body(), 0, 200));
            Log::warning("nekoBT answered {$response->status()}: {$message}");
        }
    }
}
