<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Http\Resources\ApiError\ApiErrorDetail;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Downloads are off: no qBittorrent address or no download folder is saved. The admin sets them in Settings.
 */
class DownloadClientNotConfiguredException extends RuntimeException implements ShouldntReport
{
    public function __construct()
    {
        parent::__construct('qBittorrent or the download folder is not set up.');
    }

    public function render(): JsonResponse
    {
        return ApiErrorDetail::response(ApiErrorCode::DownloadClientNotConfigured, 409);
    }
}
