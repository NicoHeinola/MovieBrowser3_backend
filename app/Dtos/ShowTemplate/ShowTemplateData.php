<?php

namespace App\Dtos\ShowTemplate;

use Spatie\LaravelData\Data;

class ShowTemplateData extends Data
{
    /**
     * A show as an outside source describes it, in the app's own terms, for the show editor to start from. Nothing
     * here is saved until the user saves the show. Seasons and episodes aren't part of it: the user adds those.
     *
     * @param  string  $sourceUrl  The page the template was made from
     * @param  list<string>  $titles  The show's titles, the one to show first
     * @param  list<string>  $genres  The source's genre names, matched to the app's categories later
     */
    public function __construct(
        public string $sourceUrl,
        public array $titles,
        public ?string $description,
        public ?string $bannerUrl,
        public ?string $cardImageUrl,
        public ?string $previewUrl,
        public array $genres,
    ) {}
}
