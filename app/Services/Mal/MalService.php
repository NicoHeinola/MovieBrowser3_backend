<?php

namespace App\Services\Mal;

use App\Dtos\Mal\MalAnimeData;
use App\Dtos\ShowTemplate\ShowTemplateData;
use App\Exceptions\MalUnavailableException;
use App\Exceptions\ShowTemplateNotFoundException;
use App\Exceptions\ShowTemplateUnavailableException;
use App\Services\ShowTemplateSource;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Talks to MyAnimeList (MAL), an anime database: it searches its anime, and describes the show behind one of its
 * anime pages (`/anime/16498/Shingeki_no_Kyojin`) as a show template. MAL's public API needs a client id, so both
 * use what its website uses: the search box's JSON and the anime page's HTML. The mappers next to it translate the
 * answers; this class only asks and checks the answers.
 */
class MalService implements ShowTemplateSource
{
    public function __construct(
        private readonly MalAnimeMapper $anime,
        private readonly MalShowTemplateMapper $showTemplates,
    ) {}

    /**
     * The anime whose names match a search, in MAL's order. MAL answers with about ten.
     *
     * @return list<MalAnimeData>
     *
     * @throws MalUnavailableException When MAL can't be reached or answers with an error
     */
    public function searchAnime(string $search): array
    {
        $response = $this->request('/search/prefix.json', ['type' => 'anime', 'keyword' => $search, 'v' => 1]);

        if ($response === null || !$response->successful() || !is_array($response->json('categories'))) {
            $this->logFailure($response);

            throw new MalUnavailableException;
        }

        return $this->anime->searchResults($response->json('categories'));
    }

    public function supportsShowTemplateUrl(string $url): bool
    {
        return $this->animeId($url) !== null;
    }

    public function getShowTemplate(string $url): ShowTemplateData
    {
        $animeId = $this->animeId($url) ?? throw new ShowTemplateNotFoundException;
        $response = $this->request("/anime/{$animeId}");

        // A mistyped or removed id is the user's to fix, not MAL being down
        if ($response?->status() === 404) {
            throw new ShowTemplateNotFoundException;
        }

        $template = $response?->successful() ? $this->showTemplates->showTemplate($url, $response->body()) : null;

        if ($template === null) {
            $this->logFailure($response);

            throw new ShowTemplateUnavailableException;
        }

        return $template;
    }

    /**
     * The anime id in a link to a MAL anime page, such as `16498` in `https://myanimelist.net/anime/16498/Shingeki`.
     */
    private function animeId(string $url): ?int
    {
        $host = preg_quote((string) parse_url(config('mal.base_url'), PHP_URL_HOST), '#');

        return preg_match("#^https?://(?:www\.)?{$host}/anime/(\d+)(?:/[^?\#]*)?(?:[?\#].*)?$#i", trim($url), $matches)
            ? (int) $matches[1]
            : null;
    }

    /**
     * Sends a GET to MAL.
     *
     * @param  array<string, string|int>  $query
     * @return Response|null The answer, or null when MAL didn't answer (logged)
     */
    private function request(string $path, array $query = []): ?Response
    {
        try {
            return Http::timeout(config('mal.timeout_seconds'))->get(config('mal.base_url').$path, $query);
        } catch (ConnectionException $exception) {
            Log::warning("MyAnimeList did not answer: {$exception->getMessage()}");

            return null;
        }
    }

    /**
     * Logs what MAL answered instead of a usable answer; a missing answer was logged when it happened.
     */
    private function logFailure(?Response $response): void
    {
        if ($response !== null) {
            $body = mb_substr($response->body(), 0, 200);
            Log::warning("MyAnimeList answered {$response->status()}: {$body}");
        }
    }
}
