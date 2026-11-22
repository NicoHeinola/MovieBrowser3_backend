<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Http\Resources\ApiError\ApiErrorDetail;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * MyAnimeList's search didn't answer or answered with something unusable. Sites going down is normal, so it isn't
 * reported; the service logs what the site answered.
 */
class MalUnavailableException extends RuntimeException implements ShouldntReport
{
    public function __construct()
    {
        parent::__construct("MyAnimeList didn't answer.");
    }

    public function render(): JsonResponse
    {
        return ApiErrorDetail::response(ApiErrorCode::MalUnavailable, 502);
    }
}
