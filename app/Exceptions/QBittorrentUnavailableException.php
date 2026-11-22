<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Enums\QBittorrentFailure;
use App\Http\Resources\ApiError\ApiErrorDetail;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * qBittorrent didn't answer, or turned the app's login away. It runs on the user's machine and may simply be closed,
 * so it isn't reported; the service logs what qBittorrent answered.
 */
class QBittorrentUnavailableException extends RuntimeException implements ShouldntReport
{
    public function __construct(public readonly QBittorrentFailure $failure)
    {
        parent::__construct("qBittorrent couldn't be used: {$failure->value}.");
    }

    public function render(): JsonResponse
    {
        return ApiErrorDetail::response(ApiErrorCode::QBittorrentUnavailable, 502, ['reason' => $this->failure->value]);
    }
}
