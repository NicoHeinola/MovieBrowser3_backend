<?php

namespace App\Actions\Mal;

use App\Dtos\Mal\MalAnimeData;
use App\Exceptions\MalUnavailableException;
use App\Services\Mal\MalService;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\LaravelData\Exceptions\CannotCreateData;

class SearchMalAnimeAction
{
    use AsAction;

    public function __construct(private readonly MalService $mal) {}

    /**
     * The MyAnimeList anime matching a search, cached for a while so typing the same name again doesn't ask MAL
     * again. A failed search isn't cached: MAL may answer the next try.
     *
     * @return list<MalAnimeData>
     *
     * @throws MalUnavailableException
     */
    public function handle(string $search): array
    {
        // Case and spacing don't change MAL's answer, so they share one cache entry
        $search = trim((string) preg_replace('/\s+/u', ' ', $search));
        // xxh128: a fast, non-cryptographic hash, enough for a cache key
        $key = 'mal.anime.'.hash('xxh128', mb_strtolower($search));

        // Cached as arrays: the cache store doesn't unserialize objects
        $cached = Cache::get($key);

        if (is_array($cached)) {
            try {
                return array_map(MalAnimeData::from(...), $cached);
            } catch (CannotCreateData) {
                // Cached before an update changed the result's shape; ask MAL again instead
            }
        }

        $results = $this->mal->searchAnime($search);
        Cache::put($key, array_map(fn (MalAnimeData $anime): array => $anime->toArray(), $results), config('mal.cache_seconds'));

        return $results;
    }
}
