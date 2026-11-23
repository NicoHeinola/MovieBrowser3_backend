<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Http\Resources\ApiError\ApiErrorDetail;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * The torrent is already a download. Its id goes along, so the frontend can carry on with that one.
 */
class DownloadExistsException extends RuntimeException implements ShouldntReport
{
    public function __construct(public readonly int $downloadId)
    {
        parent::__construct("The torrent is already download {$downloadId}.");
    }

    public function render(): JsonResponse
    {
        return ApiErrorDetail::response(ApiErrorCode::DownloadExists, 409, ['download_id' => (string) $this->downloadId]);
    }
}
