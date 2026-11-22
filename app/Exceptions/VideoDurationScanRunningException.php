<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Http\Resources\ApiError\ApiErrorDetail;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * An episode length scan is reading video files, so nothing may move them meanwhile.
 */
class VideoDurationScanRunningException extends RuntimeException implements ShouldntReport
{
    public function __construct()
    {
        parent::__construct('An episode length scan is running.');
    }

    public function render(): JsonResponse
    {
        return ApiErrorDetail::response(ApiErrorCode::VideoDurationScanRunning, 409);
    }
}
