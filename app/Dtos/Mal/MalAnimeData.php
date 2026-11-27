<?php

namespace App\Dtos\Mal;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

class MalAnimeData extends Data
{
    /**
     * One MyAnimeList anime of a search result, as MyAnimeList sends it. Details it leaves out are null.
     *
     * @param  int  $id  MyAnimeList's anime id
     * @param  string  $url  Its MyAnimeList page
     * @param  string|null  $imageUrl  A small cover thumbnail
     * @param  string|null  $mediaType  Such as `TV`, `Movie` or `ONA`
     * @param  string|null  $status  Such as `Finished Airing`
     * @param  int|null  $startYear  Null until it is known
     * @param  string|null  $score  Such as `8.40`; null until someone scores it
     */
    public function __construct(
        public int $id,
        #[MapInputName('name')]
        public string $title,
        public string $url,
        #[MapInputName('image_url')]
        public ?string $imageUrl = null,
        #[MapInputName('payload.media_type')]
        public ?string $mediaType = null,
        #[MapInputName('payload.start_year')]
        public ?int $startYear = null,
        #[MapInputName('payload.status')]
        public ?string $status = null,
        #[MapInputName('payload.score')]
        public ?string $score = null,
    ) {}

    /**
     * Changes MyAnimeList's words for "not known" to null: a score of `N/A` and a start year of 0.
     *
     * @param  array<string, mixed>  $properties  MyAnimeList's item, before the names are mapped
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $properties): array
    {
        if (($properties['payload']['score'] ?? null) === 'N/A') {
            $properties['payload']['score'] = null;
        }

        if (($properties['payload']['start_year'] ?? null) === 0) {
            $properties['payload']['start_year'] = null;
        }

        return $properties;
    }
}
