<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Http\Resources\ApiError\ApiErrorDetail;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * The link's site has no show at that address, such as a mistyped id.
 */
class ShowTemplateNotFoundException extends RuntimeException implements ShouldntReport
{
    public function __construct()
    {
        parent::__construct('The link points at no show.');
    }

    public function render(): JsonResponse
    {
        return ApiErrorDetail::response(ApiErrorCode::ShowTemplateNotFound, 404);
    }
}
