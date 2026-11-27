<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use Illuminate\Contracts\Debug\ShouldntReport;

/**
 * qBittorrent no longer has the download's torrent, such as after someone removed it there.
 */
class DownloadNotInClientException extends ApiException implements ShouldntReport
{
    public function __construct()
    {
        parent::__construct(ApiErrorCode::DownloadNotInClient, 409, 'qBittorrent no longer has this torrent.');
    }
}
