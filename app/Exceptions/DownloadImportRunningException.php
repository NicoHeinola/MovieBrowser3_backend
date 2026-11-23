<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Http\Resources\ApiError\ApiErrorDetail;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A download's finished files are being copied into their episodes, so it can't be cancelled until they are in place:
 * the copy holds its episodes, and a cancel would leave a copied video without its episode.
 */
class DownloadImportRunningException extends RuntimeException implements ShouldntReport
{
    public function __construct()
    {
        parent::__construct("The download's files are being copied into their episodes.");
    }

    public function render(): JsonResponse
    {
        return ApiErrorDetail::response(ApiErrorCode::DownloadImportRunning, 409);
    }
}
