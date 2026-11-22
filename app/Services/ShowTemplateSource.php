<?php

namespace App\Services;

use App\Dtos\ShowTemplate\ShowTemplateData;
use App\Exceptions\ShowTemplateNotFoundException;
use App\Exceptions\ShowTemplateUnavailableException;

/**
 * An outside site that can describe a show in the app's own terms, from a link to its page. Each source is a service
 * tagged with this interface in `AppServiceProvider`; the first one that supports a link answers for it.
 */
interface ShowTemplateSource
{
    /**
     * Whether a link points at one of this source's show pages.
     */
    public function supportsShowTemplateUrl(string $url): bool;

    /**
     * The show behind a supported link.
     *
     * @throws ShowTemplateNotFoundException When the source has no such show
     * @throws ShowTemplateUnavailableException When the source can't be reached or answers with an error
     */
    public function getShowTemplate(string $url): ShowTemplateData;
}
