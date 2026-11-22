<?php

namespace App\Dtos\ShowTemplate;

use App\Models\Category\Category;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class MatchedShowTemplateData extends Data
{
    /**
     * A show template with its genres matched to the app's categories.
     *
     * @param  Collection<int, Category>  $categories  The categories whose name or value matches a genre
     * @param  list<string>  $unmatchedGenres  Genres no category matches
     */
    public function __construct(
        public ShowTemplateData $template,
        public Collection $categories,
        public array $unmatchedGenres,
    ) {}
}
