<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Enums\QBittorrentFailure;
use Illuminate\Contracts\Debug\ShouldntReport;

/**
 * qBittorrent didn't answer, or turned the app's login away. It runs on the user's machine and may simply be closed,
 * so it isn't reported; the service logs what qBittorrent answered.
 */
class QBittorrentUnavailableException extends ApiException implements ShouldntReport
{
    public function __construct(public readonly QBittorrentFailure $failure)
    {
        parent::__construct(ApiErrorCode::QBittorrentUnavailable, 502, "qBittorrent couldn't be used: {$failure->value}.", ['reason' => $failure->value]);
    }
}
