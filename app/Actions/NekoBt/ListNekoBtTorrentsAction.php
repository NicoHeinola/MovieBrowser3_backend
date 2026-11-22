<?php

namespace App\Actions\NekoBt;

use App\Dtos\NekoBt\NekoBtSearchData;
use App\Dtos\NekoBt\NekoBtTorrentPageData;
use App\Exceptions\TorrentSiteUnavailableException;
use App\Services\NekoBt\NekoBtService;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\LaravelData\Exceptions\CannotCastDate;
use Spatie\LaravelData\Exceptions\CannotCastEnum;
use Spatie\LaravelData\Exceptions\CannotCreateData;

class ListNekoBtTorrentsAction
{
    use AsAction;

    public function __construct(private readonly NekoBtService $nekoBt) {}

    /**
     * One page of nekoBT torrents for a search, cached for a while so paging back and forth doesn't ask nekoBT again. A
     * failed search isn't cached: nekoBT may answer the next try.
     *
     * @throws TorrentSiteUnavailableException
     */
    public function handle(NekoBtSearchData $search): NekoBtTorrentPageData
    {
        // xxh128: a fast, non-cryptographic hash, enough for a cache key
        $key = 'torrents.nekobt.'.hash('xxh128', (string) json_encode($search->toArray()));

        // Cached as an array: the cache store doesn't unserialize objects
        $cached = Cache::get($key);

        if (is_array($cached)) {
            try {
                return NekoBtTorrentPageData::from($cached);
            } catch (CannotCreateData|CannotCastEnum|CannotCastDate) {
                // Cached before an update changed the page's shape (a field, an enum case or a date format); ask nekoBT
                // again instead
            }
        }

        $page = $this->nekoBt->getTorrents($search);
        Cache::put($key, $page->toArray(), config('torrents.cache_seconds'));

        return $page;
    }
}
