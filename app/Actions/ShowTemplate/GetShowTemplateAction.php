<?php

namespace App\Actions\ShowTemplate;

use App\Dtos\ShowTemplate\MatchedShowTemplateData;
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

        $template = $source->getShowTemplate($url);

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
}
