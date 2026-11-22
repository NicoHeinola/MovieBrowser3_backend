<?php

namespace App\Dtos\Mal;

use Spatie\LaravelData\Data;

class MalAnimeData extends Data
{
    /**
     * One MyAnimeList anime of a search result. Details MyAnimeList leaves out are null.
     *
     * @param  int  $id  MyAnimeList's anime id
     * @param  string  $url  Its MyAnimeList page
     * @param  string|null  $imageUrl  A small cover thumbnail
     * @param  string|null  $mediaType  Such as `TV`, `Movie` or `ONA`, as MyAnimeList writes it
     * @param  string|null  $status  Such as `Finished Airing`, as MyAnimeList writes it
     * @param  string|null  $score  MyAnimeList's score as it writes it, such as `8.40`; null before anyone scored it
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $url,
        public ?string $imageUrl,
        public ?string $mediaType,
        public ?int $startYear,
        public ?string $status,
        public ?string $score,
    ) {}
}
