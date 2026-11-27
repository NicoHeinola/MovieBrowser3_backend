<?php

namespace App\Actions\ShowTemplate;

use App\Services\Mal\MalService;
use App\Services\NekoBt\NekoBtService;
use App\Services\ShowTemplateSource;
use Lorisleiva\Actions\Concerns\AsAction;

class ResolveShowTemplateSourceAction
{
    use AsAction;

    public function __construct(
        private readonly NekoBtService $nekoBtService,
        private readonly MalService $malService,
    ) {}

    /**
     * The template source that reads a link, or null when none does.
     */
    public function handle(string $url): ?ShowTemplateSource
    {
        // Asked in this order; another site is another service here
        $sources = [$this->nekoBtService, $this->malService];

        foreach ($sources as $source) {
            if ($source->supportsShowTemplateUrl($url)) {
                return $source;
            }
        }

        return null;
    }
}
