<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Http\Resources\ApiError\ApiErrorDetail;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * qBittorrent no longer has the download's torrent, such as after someone removed it there.
 */
class DownloadNotInClientException extends RuntimeException implements ShouldntReport
{
    public function __construct()
    {
        parent::__construct('qBittorrent no longer has this torrent.');
    }

    public function render(): JsonResponse
    {
        return ApiErrorDetail::response(ApiErrorCode::DownloadNotInClient, 409);
    }
}
