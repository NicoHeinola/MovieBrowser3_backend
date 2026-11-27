<?php

namespace App\Dtos\NekoBt;

use App\Dtos\ShowTemplate\ShowTemplateData;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

class NekoBtMediaData extends Data
{
    /**
     * A show or movie as nekoBT describes it, with what it knows from AniList.
     *
     * @param  list<string>|null  $alternateTitles
     * @param  string|null  $overview  Plain text
     * @param  string|null  $posterUrl  nekoBT calls it a banner, but it has a poster's shape
     * @param  list<string>|null  $genres
     * @param  string|null  $anilistDescriptionHtml  AniList's description, with HTML tags
     * @param  string|null  $anilistBannerUrl  Wide
     * @param  string|null  $anilistCoverUrl  A poster
     */
    public function __construct(
        public ?string $title = null,
        #[MapInputName('alternate_titles')]
        public ?array $alternateTitles = null,
        public ?string $overview = null,
        #[MapInputName('banner_url')]
        public ?string $posterUrl = null,
        public ?array $genres = null,
        #[MapInputName('anilist.primary.title.english')]
        public ?string $anilistEnglishTitle = null,
        #[MapInputName('anilist.primary.title.romaji')]
        public ?string $anilistRomajiTitle = null,
        #[MapInputName('anilist.primary.description')]
        public ?string $anilistDescriptionHtml = null,
        #[MapInputName('anilist.primary.banner_image')]
        public ?string $anilistBannerUrl = null,
        #[MapInputName('anilist.primary.cover_image')]
        public ?string $anilistCoverUrl = null,
    ) {}

    /**
     * @param  string  $sourceUrl  The media page the user linked
     */
    public function toShowTemplate(string $sourceUrl): ShowTemplateData
    {
        // nekoBT's own title first; `GetShowTemplateAction` picks which ones to keep
        $titles = [$this->title, $this->anilistEnglishTitle, $this->anilistRomajiTitle, ...$this->alternateTitles ?? []];

        return new ShowTemplateData(
            sourceUrl: $sourceUrl,
            titles: array_values(array_filter($titles)),
            description: $this->overview ?: $this->getPlainAnilistDescription(),
            bannerUrl: $this->anilistBannerUrl,
            cardImageUrl: $this->anilistCoverUrl ?: $this->posterUrl,
            previewUrl: null,
            genres: $this->genres ?? [],
        );
    }

    /**
     * Line breaks are kept, tags dropped and entities decoded.
     */
    private function getPlainAnilistDescription(): ?string
    {
        if ($this->anilistDescriptionHtml === null) {
            return null;
        }

        $textWithLineBreaks = preg_replace('#<br\s*/?>#i', "\n", $this->anilistDescriptionHtml);

        return html_entity_decode(strip_tags($textWithLineBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
