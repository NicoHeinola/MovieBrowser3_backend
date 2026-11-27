<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use Illuminate\Contracts\Debug\ShouldntReport;

/**
 * The torrent is already a download. Its id goes along, so the frontend can carry on with that one.
 */
class DownloadExistsException extends ApiException implements ShouldntReport
{
    public function __construct(public readonly int $downloadId)
    {
        parent::__construct(ApiErrorCode::DownloadExists, 409, "The torrent is already download {$downloadId}.", ['download_id' => (string) $downloadId]);
    }
}
