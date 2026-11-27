<?php

namespace App\Services\Mal;

use App\Dtos\ShowTemplate\ShowTemplateData;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Str;

/**
 * Turns a MyAnimeList anime page (`/anime/16498`) into a show template. MyAnimeList has no public API without a
 * client id, so this reads the page's HTML: the title heading, the "Alternative Titles" and "Information" sidebar,
 * the synopsis and the `og:image` cover. Only `MalService` uses it.
 */
final class MalAnimePageParser
{
    /**
     * @param  string  $url  The anime page the user linked
     * @param  string  $html  The page
     * @return ShowTemplateData|null Null when the page has no title, so it isn't an anime page as expected
     */
    public function parseShowTemplate(string $url, string $html): ?ShowTemplateData
    {
        // MyAnimeList's HTML isn't valid everywhere; the HTML5 parser copes like a browser and the errors don't matter
        $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);

        $mainTitle = $this->getTextOrNull($document->querySelector('h1.title-name')?->textContent)
            ?? $this->getTextOrNull($document->querySelector('meta[property="og:title"]')?->getAttribute('content'));

        if ($mainTitle === null) {
            return null;
        }

        $sidebar = $this->getSidebarValues($document);

        return new ShowTemplateData(
            sourceUrl: $url,
            // The page's own title first, then English, the synonyms and Japanese
            titles: array_values(array_filter([
                $mainTitle,
                $this->getTextOrNull($document->querySelector('p.title-english')?->textContent) ?? $sidebar['English'] ?? null,
                ...array_map(trim(...), explode(',', $sidebar['Synonyms'] ?? '')),
                $sidebar['Japanese'] ?? null,
            ], fn (?string $title): bool => $title !== null && $title !== '')),
            description: $this->getDescription($document->querySelector('p[itemprop="description"]')),
            bannerUrl: null,
            cardImageUrl: $this->getLargeImageUrl($document->querySelector('meta[property="og:image"]')?->getAttribute('content')),
            previewUrl: null,
            // Genres, themes and the demographic, each written once in a hidden `itemprop="genre"` span
            genres: collect($document->querySelectorAll('[itemprop="genre"]'))
                ->map(fn (Element $genre): ?string => $this->getTextOrNull($genre->textContent))
                ->filter()
                ->unique()
                ->values()
                ->all(),
        );
    }

    /**
     * The sidebar's labelled values, such as `Japanese` → `進撃の巨人`: each `<span class="dark_text">Label:</span>` is
     * followed by its value inside the same block. The first block with a label wins.
     *
     * @return array<string, string>
     */
    private function getSidebarValues(HTMLDocument $document): array
    {
        $values = [];

        foreach ($document->querySelectorAll('span.dark_text') as $label) {
            $name = rtrim(trim((string) $label->textContent), ':');
            $block = $label->parentElement;

            if ($name === '' || $block === null || array_key_exists($name, $values)) {
                continue;
            }

            // The block's text without the label in front
            $value = $this->getTextOrNull(Str::after((string) $block->textContent, (string) $label->textContent));

            if ($value !== null) {
                $values[$name] = $value;
            }
        }

        return $values;
    }

    /**
     * The synopsis as plain text: `<br>` becomes a line break, and MyAnimeList's "[Written by MAL Rewrite]" credit is
     * left out. Null when the page has none, or only MyAnimeList's "No synopsis" placeholder.
     */
    private function getDescription(?Element $synopsis): ?string
    {
        if ($synopsis === null) {
            return null;
        }

        $text = '';

        foreach ($synopsis->childNodes as $node) {
            // Whitespace in the page's source (line breaks, indentation) is one space, as a browser shows it; `<br>` is
            // the only visible line break
            if ($node instanceof Element && $node->localName === 'br') {
                $text .= "\n";
            } else {
                $text .= preg_replace('/\s+/u', ' ', (string) $node->textContent);
            }
        }

        $text = (string) preg_replace('/\[Written by [^\]]*\]/i', '', $text);
        // The spaces left around each line break go, and at most one empty line stays between paragraphs
        $text = (string) preg_replace('/ *\n */', "\n", $text);
        $text = trim((string) preg_replace("/\n{3,}/", "\n\n", $text));

        if ($text === '' || str_starts_with($text, 'No synopsis')) {
            return null;
        }

        return $text;
    }

    /**
     * The large version of a MyAnimeList cover: `…/47347.jpg` becomes `…/47347l.jpg`. Other links are kept as they are.
     */
    private function getLargeImageUrl(?string $url): ?string
    {
        $url = $this->getTextOrNull($url);

        if ($url === null) {
            return null;
        }

        return preg_replace('#(/images/anime/\d+/\d+)(\.\w+)$#', '$1l$2', $url);
    }

    /**
     * A trimmed text with runs of whitespace as one space, or null for an empty one.
     */
    private function getTextOrNull(?string $value): ?string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', (string) $value));

        return $value === '' ? null : $value;
    }
}
