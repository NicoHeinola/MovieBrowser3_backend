<?php

namespace App\Actions\ShowTemplate;

use App\Services\ShowTemplateSource;
use Lorisleiva\Actions\Concerns\AsAction;

class ResolveShowTemplateSourceAction
{
    use AsAction;

    /**
     * The template source that reads a link: the first tagged source that supports it, in `AppServiceProvider`'s order.
     *
     * @return ShowTemplateSource|null Null when no source reads the link
     */
    public function handle(string $url): ?ShowTemplateSource
    {
        return collect(app()->tagged(ShowTemplateSource::class))
            ->first(fn (ShowTemplateSource $source): bool => $source->supportsShowTemplateUrl($url));
    }
}
