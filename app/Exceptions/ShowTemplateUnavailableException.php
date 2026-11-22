<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Http\Resources\ApiError\ApiErrorDetail;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * The link's site didn't answer or answered with an error. Sites going down is normal, so it isn't reported; the
 * service logs what the site answered.
 */
class ShowTemplateUnavailableException extends RuntimeException implements ShouldntReport
{
    public function __construct()
    {
        parent::__construct("The link's site didn't answer.");
    }

    public function render(): JsonResponse
    {
        return ApiErrorDetail::response(ApiErrorCode::ShowTemplateUnavailable, 502);
    }
}
