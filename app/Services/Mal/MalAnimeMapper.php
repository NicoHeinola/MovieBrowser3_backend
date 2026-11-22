<?php

namespace App\Services\Mal;

use App\Dtos\Mal\MalAnimeData;

/**
 * Turns MyAnimeList's search answer (`/search/prefix.json?type=anime`) into anime search results. Only `MalService`
 * uses it.
 */
final class MalAnimeMapper
{
    /**
     * The anime in the answer's categories, in MyAnimeList's order. Items without an id, name or web link are
     * skipped.
     *
     * @param  array<int, mixed>  $categories  The answer's `categories`, each `{ type, items }`
     * @return list<MalAnimeData>
     */
    public function searchResults(array $categories): array
    {
        $results = [];

        foreach ($categories as $category) {
            if (!is_array($category) || ($category['type'] ?? null) !== 'anime' || !is_array($category['items'] ?? null)) {
                continue;
            }

            foreach ($category['items'] as $item) {
                $anime = is_array($item) ? $this->anime($item) : null;

                if ($anime !== null) {
                    $results[] = $anime;
                }
            }
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function anime(array $item): ?MalAnimeData
    {
        $id = $item['id'] ?? null;
        $title = $this->text($item['name'] ?? null);
        $url = $this->text($item['url'] ?? null);

        // The link is opened and filled in from as it is, so only a web address will do
        if (!is_int($id) || $title === null || $url === null || !$this->isWebUrl($url)) {
            return null;
        }

        $payload = is_array($item['payload'] ?? null) ? $item['payload'] : [];
        $score = $this->text($payload['score'] ?? null);

        return new MalAnimeData(
            id: $id,
            title: $title,
            url: $url,
            imageUrl: $this->text($item['image_url'] ?? null),
            mediaType: $this->text($payload['media_type'] ?? null),
            startYear: is_int($payload['start_year'] ?? null) && $payload['start_year'] > 0 ? $payload['start_year'] : null,
            status: $this->text($payload['status'] ?? null),
            // MyAnimeList writes `N/A` until someone scores it
            score: $score !== null && is_numeric($score) ? $score : null,
        );
    }

    /**
     * Whether a link is an http or https address.
     */
    private function isWebUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    /**
     * A trimmed text, or null for anything else or an empty one.
     */
    private function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
