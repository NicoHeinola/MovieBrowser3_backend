<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use Illuminate\Contracts\Debug\ShouldntReport;

/**
 * MyAnimeList's search didn't answer or answered with something unusable. Sites going down is normal, so it isn't
 * reported; the service logs what the site answered.
 */
class MalUnavailableException extends ApiException implements ShouldntReport
{
    public function __construct()
    {
        parent::__construct(ApiErrorCode::MalUnavailable, 502, "MyAnimeList didn't answer.");
    }
}
