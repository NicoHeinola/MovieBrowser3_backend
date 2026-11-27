<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use Illuminate\Contracts\Debug\ShouldntReport;

/**
 * The link's site has no show at that address, such as a mistyped id.
 */
class ShowTemplateNotFoundException extends ApiException implements ShouldntReport
{
    public function __construct()
    {
        parent::__construct(ApiErrorCode::ShowTemplateNotFound, 404, 'The link points at no show.');
    }
}
