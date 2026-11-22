<?php

namespace App\Services\NekoBt;

use App\Dtos\ShowTemplate\ShowTemplateData;

/**
 * Turns a nekoBT media answer (`/api/v1/media/s1453`) into a show template. nekoBT embeds the show's AniList entry in
 * its answer, which brings the romaji title and the images. Only `NekoBtService` uses it.
 */
final class NekoBtShowTemplateMapper
{
    /**
     * @param  string  $url  The media page the user linked
     * @param  array<string, mixed>  $media  The answer's `data`
     */
    public function showTemplate(string $url, array $media): ShowTemplateData
    {
        $anilist = is_array($media['anilist']['primary'] ?? null) ? $media['anilist']['primary'] : [];

        return new ShowTemplateData(
            sourceUrl: $url,
            titles: $this->titles($this->text($media['title'] ?? null), [
                $media['title'] ?? null,
                $anilist['title']['english'] ?? null,
                $anilist['title']['romaji'] ?? null,
                ...(is_array($media['alternate_titles'] ?? null) ? $media['alternate_titles'] : []),
            ]),
            // nekoBT's overview is plain text, so it comes before AniList's HTML description
            description: $this->text($media['overview'] ?? null) ?? $this->text($this->plainText($anilist['description'] ?? null)),
            // AniList's wide banner; nekoBT has none of its own
            bannerUrl: $this->text($anilist['banner_image'] ?? null),
            // AniList's cover, or nekoBT's own image, which is a poster, the same shape as a card
            cardImageUrl: $this->text($anilist['cover_image'] ?? null) ?? $this->text($media['banner_url'] ?? null),
            previewUrl: null,
            genres: array_values(array_filter(
                is_array($media['genres'] ?? null) ? $media['genres'] : [],
                fn (mixed $genre): bool => is_string($genre) && $genre !== '',
            )),
        );
    }

    /**
     * The titles to keep, in order and without repeats (ignoring case): only ones in Latin script, since a show is
     * searched by those, and native-script titles (Japanese, Russian) would crowd the list.
     *
     * @param  string|null  $mainTitle  nekoBT's own title, kept whatever its script when no title is in Latin script
     * @param  list<mixed>  $candidates
     * @return list<string>
     */
    private function titles(?string $mainTitle, array $candidates): array
    {
        $titles = collect($candidates)
            ->map(fn (mixed $title): ?string => $this->text($title))
            ->filter(fn (?string $title): bool => $title !== null
                && preg_match('/^[\p{Latin}\p{N}\p{P}\p{S}\p{Zs}]+$/u', $title) === 1)
            ->unique(fn (string $title): string => mb_strtolower($title))
            ->values()
            ->all();

        // A show always gets a title to start from
        return $titles === [] && $mainTitle !== null ? [$mainTitle] : $titles;
    }

    /**
     * A trimmed text, or null for anything else or an empty one.
     */
    private function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * Text from an HTML description, such as AniList's: line breaks kept, tags dropped and entities decoded.
     */
    private function plainText(mixed $html): ?string
    {
        if (!is_string($html)) {
            return null;
        }

        $text = strip_tags((string) preg_replace('#<br\s*/?>#i', "\n", $html));

        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
