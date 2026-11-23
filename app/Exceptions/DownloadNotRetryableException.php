<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Http\Resources\ApiError\ApiErrorDetail;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Only a failed import can be tried again; a torrent qBittorrent lost is added again instead.
 */
class DownloadNotRetryableException extends RuntimeException implements ShouldntReport
{
    public function __construct()
    {
        parent::__construct('This download can not be tried again.');
    }

    public function render(): JsonResponse
    {
        return ApiErrorDetail::response(ApiErrorCode::DownloadNotRetryable, 409);
    }
}
