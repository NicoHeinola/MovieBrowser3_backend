<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use Illuminate\Contracts\Debug\ShouldntReport;

/**
 * The link's site didn't answer or answered with an error. Sites going down is normal, so it isn't reported; the
 * service logs what the site answered.
 */
class ShowTemplateUnavailableException extends ApiException implements ShouldntReport
{
    public function __construct()
    {
        parent::__construct(ApiErrorCode::ShowTemplateUnavailable, 502, "The link's site didn't answer.");
    }
}
