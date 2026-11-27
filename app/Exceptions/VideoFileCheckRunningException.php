<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use Illuminate\Contracts\Debug\ShouldntReport;

/**
 * A video file check is running, so nothing may move the files it is looking at.
 */
class VideoFileCheckRunningException extends ApiException implements ShouldntReport
{
    public function __construct()
    {
        parent::__construct(ApiErrorCode::VideoFileCheckRunning, 409, 'A video file check is running.');
    }
}
