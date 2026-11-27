<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use Illuminate\Contracts\Debug\ShouldntReport;

/**
 * Downloads are off: no qBittorrent address or no download folder is saved. The admin sets them in Settings.
 */
class DownloadClientNotConfiguredException extends ApiException implements ShouldntReport
{
    public function __construct()
    {
        parent::__construct(ApiErrorCode::DownloadClientNotConfigured, 409, 'qBittorrent or the download folder is not set up.');
    }
}
