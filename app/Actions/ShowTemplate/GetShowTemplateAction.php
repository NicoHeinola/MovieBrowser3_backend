<?php

namespace App\Actions\ShowTemplate;

use App\Dtos\ShowTemplate\MatchedShowTemplateData;
use App\Dtos\ShowTemplate\ShowTemplateData;
use App\Exceptions\ShowTemplateNotFoundException;
use App\Exceptions\ShowTemplateUnavailableException;
use App\Models\Category\Category;
use Illuminate\Support\Str;
use LogicException;
use Lorisleiva\Actions\Concerns\AsAction;

class GetShowTemplateAction
{
    use AsAction;

    /**
     * The show behind a link, from the first template source that supports it, with its genres matched to the app's
     * categories by name or value, ignoring case, in the source's genre order.
     *
     * The link must be one a source reads; the request checks that (`ShowTemplateUrl`), so it is checked once.
     *
     * @throws ShowTemplateNotFoundException When the link's source has no such show
     * @throws ShowTemplateUnavailableException When the source can't be reached
     */
    public function handle(string $url): MatchedShowTemplateData
    {
        $source = ResolveShowTemplateSourceAction::run($url)
            ?? throw new LogicException("No show template source reads {$url}; validate the link first.");

        $template = $this->withKeptTitles($source->getShowTemplate($url));

        // A genre matches a category whose name it equals, or whose value it equals once slugged ("Sci-Fi" → "sci-fi")
        $matches = fn (Category $category, string $genre): bool => mb_strtolower($category->name) === mb_strtolower($genre)
            || $category->value === Str::slug($genre);

        // Each genre with the category it matches, if any, in the source's genre order
        $categories = Category::query()->get();
        $genreCategories = collect($template->genres)->map(fn (string $genre): array => [
            $genre,
            $categories->first(fn (Category $category): bool => $matches($category, $genre)),
        ]);

        $matched = $genreCategories->pluck(1)->filter()->unique('id')->values();
        $unmatchedGenres = $genreCategories->filter(fn (array $pair): bool => $pair[1] === null)->pluck(0)->values()->all();

        return new MatchedShowTemplateData($template, $matched, $unmatchedGenres);
    }

    /**
     * The template with only the titles to keep, in order and without repeats (ignoring case): only ones in Latin
     * script, since a show is searched by those, and native-script titles (Japanese, Russian) would crowd the list.
     * When none is in Latin script, the source's first title stays, so a show always gets a title to start from.
     */
    private function withKeptTitles(ShowTemplateData $template): ShowTemplateData
    {
        $titles = collect($template->titles)
            ->filter(fn (string $title): bool => preg_match('/^[\p{Latin}\p{N}\p{P}\p{S}\p{Zs}]+$/u', $title) === 1)
            ->unique(fn (string $title): string => mb_strtolower($title))
            ->values()
            ->all();

        return new ShowTemplateData(
            sourceUrl: $template->sourceUrl,
            titles: $titles === [] ? array_slice($template->titles, 0, 1) : $titles,
            description: $template->description,
            bannerUrl: $template->bannerUrl,
            cardImageUrl: $template->cardImageUrl,
            previewUrl: $template->previewUrl,
            genres: $template->genres,
        );
    }
}
