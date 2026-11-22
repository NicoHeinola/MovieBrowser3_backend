<?php

namespace App\Dtos\NekoBt;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class NekoBtTorrentPageData extends Data
{
    /**
     * One page of a nekoBT search. nekoBT doesn't say how many results there are in all, only whether more follow.
     *
     * @param  list<NekoBtTorrentData>  $torrents
     */
    public function __construct(
        #[DataCollectionOf(NekoBtTorrentData::class)]
        public array $torrents,
        public int $page,
        public int $perPage,
        public bool $hasMore,
    ) {}
}
