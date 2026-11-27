<?php

namespace App\Services\Mal;

use App\Dtos\Mal\MalAnimeData;
use App\Dtos\ShowTemplate\ShowTemplateData;
use App\Exceptions\MalUnavailableException;
use App\Exceptions\ShowTemplateNotFoundException;
use App\Exceptions\ShowTemplateUnavailableException;
use App\Services\Mal\Requests\GetAnimePageRequest;
use App\Services\Mal\Requests\SearchAnimeRequest;
use App\Services\RemembersJson;
use App\Services\ShowTemplateSource;
use Illuminate\Support\Facades\Log;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * Talks to MyAnimeList (MAL), an anime database: it searches its anime, and describes the show behind one of its
 * anime pages (`/anime/16498/Shingeki_no_Kyojin`) as a show template. MAL's public API needs a client id, so both
 * use what its website uses: the search box's JSON and the anime page's HTML. The connector and requests next to it
 * describe the calls and the mappers translate the answers; this class only asks and checks the answers.
 */
class MalService implements ShowTemplateSource
{
    use RemembersJson;

    public function __construct(private readonly MalAnimePageParser $animePageParser) {}

    /**
     * The anime whose names match a search, in MAL's order. MAL answers with about ten.
     *
     * @return list<MalAnimeData>
     *
     * @throws MalUnavailableException When MAL can't be reached or answers with an error
     */
    public function searchAnime(string $search): array
    {
        $request = new SearchAnimeRequest($search);
        $categories = $this->rememberJson($request, config('mal.cache_seconds'), fn (): array => $this->fetchSearchCategories($request));

        return collect($categories)
            ->where('type', 'anime')
            ->pluck('items')
            ->flatten(1)
            ->map(fn (array $item): MalAnimeData => MalAnimeData::from($item))
            ->values()
            ->all();
    }

    public function supportsShowTemplateUrl(string $url): bool
    {
        return $this->getAnimeIdFromUrl($url) !== null;
    }

    public function getShowTemplate(string $url): ShowTemplateData
    {
        $animeId = $this->getAnimeIdFromUrl($url);

        if ($animeId === null) {
            throw new ShowTemplateNotFoundException;
        }

        $response = $this->sendRequest(new GetAnimePageRequest($animeId));

        // A mistyped or removed id is the user's to fix, not MAL being down
        if ($response?->status() === 404) {
            throw new ShowTemplateNotFoundException;
        }

        $template = null;

        if ($response?->successful()) {
            $template = $this->animePageParser->parseShowTemplate($url, $response->body());
        }

        if ($template === null) {
            $this->logFailedResponse($response);

            throw new ShowTemplateUnavailableException;
        }

        return $template;
    }

    /**
     * The anime id in a link to a MAL anime page, such as `16498` in `https://myanimelist.net/anime/16498/Shingeki`.
     */
    private function getAnimeIdFromUrl(string $url): ?int
    {
        $host = preg_quote((string) parse_url(config('mal.base_url'), PHP_URL_HOST), '#');

        if (!preg_match("#^https?://(?:www\.)?{$host}/anime/(\d+)(?:/[^?\#]*)?(?:[?\#].*)?$#i", trim($url), $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * Sends a request to MAL.
     *
     * @return Response|null The answer, or null when MAL didn't answer (logged)
     */
    private function sendRequest(Request $request): ?Response
    {
        try {
            return (new MalConnector)->send($request);
        } catch (FatalRequestException $exception) {
            Log::warning("MyAnimeList did not answer: {$exception->getMessage()}");

            return null;
        }
    }

    /**
     * Logs what MAL answered instead of a usable answer; a missing answer was logged when it happened.
     */
    private function logFailedResponse(?Response $response): void
    {
        if ($response !== null) {
            $body = mb_substr($response->body(), 0, 200);
            Log::warning("MyAnimeList answered {$response->status()}: {$body}");
        }
    }

    /**
     * @return list<array<string, mixed>> Each `{ type, items }`
     *
     * @throws MalUnavailableException
     */
    private function fetchSearchCategories(SearchAnimeRequest $request): array
    {
        $response = $this->sendRequest($request);

        if ($response === null || !$response->successful() || !is_array($response->json('categories'))) {
            $this->logFailedResponse($response);

            throw new MalUnavailableException;
        }

        return $response->json('categories');
    }
}
