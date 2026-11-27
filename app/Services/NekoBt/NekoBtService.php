<?php

namespace App\Services\NekoBt;

use App\Dtos\NekoBt\NekoBtMediaData;
use App\Dtos\NekoBt\NekoBtSearchData;
use App\Dtos\NekoBt\NekoBtTorrentData;
use App\Dtos\NekoBt\NekoBtTorrentPageData;
use App\Dtos\ShowTemplate\ShowTemplateData;
use App\Exceptions\ShowTemplateNotFoundException;
use App\Exceptions\ShowTemplateUnavailableException;
use App\Exceptions\TorrentSiteUnavailableException;
use App\Services\NekoBt\Requests\GetMediaRequest;
use App\Services\NekoBt\Requests\SearchTorrentsRequest;
use App\Services\RemembersJson;
use App\Services\ShowTemplateSource;
use Illuminate\Support\Facades\Log;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * Talks to nekoBT, an anime torrent tracker, through its public JSON API: it searches its torrents, and describes the
 * show behind one of its media pages (`/media/s1453` for a series, `/media/m134` for a movie) as a show template.
 * The connector and requests next to it describe the calls and the mappers translate nekoBT's answers; this class only
 * asks and checks the answers.
 */
class NekoBtService implements ShowTemplateSource
{
    /** Results per page: the API's own default, and few enough rows to show at once */
    public const PER_PAGE = 50;

    use RemembersJson;

    /**
     * One page of torrents matching the search.
     *
     * @throws TorrentSiteUnavailableException When nekoBT can't be reached or answers with an error
     */
    public function getTorrents(NekoBtSearchData $search): NekoBtTorrentPageData
    {
        $request = new SearchTorrentsRequest($search, self::PER_PAGE);
        $page = $this->rememberJson($request, config('nekobt.cache_seconds'), fn (): array => $this->fetchTorrentPage($request));

        $torrents = collect($page['results'])
            ->filter(fn (array $result): bool => $this->isPublicTorrent($result))
            ->map(fn (array $result): NekoBtTorrentData => NekoBtTorrentData::fromNekoBt($result));

        // Past the deepest page the API allows there is no next page, whatever nekoBT says
        $hasMore = ($page['more'] ?? false) && $search->page < NekoBtSearchData::MAX_PAGE;

        return new NekoBtTorrentPageData($torrents->values()->all(), $search->page, self::PER_PAGE, $hasMore);
    }

    public function supportsShowTemplateUrl(string $url): bool
    {
        return $this->getMediaIdFromUrl($url) !== null;
    }

    public function getShowTemplate(string $url): ShowTemplateData
    {
        $mediaId = $this->getMediaIdFromUrl($url);

        if ($mediaId === null) {
            throw new ShowTemplateNotFoundException;
        }

        $response = $this->sendRequest(new GetMediaRequest($mediaId));

        // A mistyped or removed id is the user's to fix, not nekoBT being down
        if ($response?->status() === 404) {
            throw new ShowTemplateNotFoundException;
        }

        if ($response === null || !$this->isSuccessfulResponse($response) || !is_array($response->json('data'))) {
            $this->logFailedResponse($response);

            throw new ShowTemplateUnavailableException;
        }

        $media = NekoBtMediaData::from($response->json('data'));

        return $media->toShowTemplate($url);
    }

    /**
     * The media id in a link to a nekoBT media page, such as `s1453` in `https://nekobt.to/media/s1453`: `s` for a
     * series, `m` for a movie.
     */
    private function getMediaIdFromUrl(string $url): ?string
    {
        $host = preg_quote((string) parse_url(config('nekobt.base_url'), PHP_URL_HOST), '#');

        if (!preg_match("#^https?://(?:www\.)?{$host}/media/([sm]\d+)/?(?:[?\#].*)?$#i", trim($url), $matches)) {
            return null;
        }

        return strtolower($matches[1]);
    }

    /**
     * Sends a request to the API.
     *
     * @return Response|null The answer, or null when nekoBT didn't answer (logged)
     */
    private function sendRequest(Request $request): ?Response
    {
        try {
            return (new NekoBtConnector)->send($request);
        } catch (FatalRequestException $exception) {
            Log::warning("nekoBT did not answer: {$exception->getMessage()}");

            return null;
        }
    }

    /**
     * Whether a response is a successful API answer. The API wraps every answer in `{ error, data }` and reports
     * failures as `{ error: true, message }`. A bot check answers with an HTML page instead.
     */
    private function isSuccessfulResponse(Response $response): bool
    {
        return $response->successful() && $response->json('error') === false;
    }

    /**
     * Logs what nekoBT answered instead of a usable answer; a missing answer was logged when it happened.
     */
    private function logFailedResponse(?Response $response): void
    {
        if ($response !== null) {
            $message = $response->json('message') ?? mb_substr($response->body(), 0, 200);
            Log::warning("nekoBT answered {$response->status()}: {$message}");
        }
    }

    /**
     * @return array{results: list<array<string, mixed>>, more?: bool}
     *
     * @throws TorrentSiteUnavailableException
     */
    private function fetchTorrentPage(SearchTorrentsRequest $request): array
    {
        $response = $this->sendRequest($request);

        if ($response === null || !$this->isSuccessfulResponse($response) || !is_array($response->json('data.results'))) {
            $this->logFailedResponse($response);

            throw new TorrentSiteUnavailableException('nekobt');
        }

        return $response->json('data');
    }

    /**
     * Hidden and deleted uploads still come back in some results, and private ones have only a private magnet: none of
     * them has a public magnet to download.
     *
     * @param  array<string, mixed>  $result
     */
    private function isPublicTorrent(array $result): bool
    {
        $isHidden = (bool) ($result['hidden'] ?? false);
        $isDeleted = isset($result['deleted']);

        return !$isHidden && !$isDeleted && isset($result['magnet']);
    }
}
