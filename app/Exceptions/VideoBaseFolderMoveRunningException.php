<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use Illuminate\Contracts\Debug\ShouldntReport;

/**
 * Video files are being moved to another video base folder, so nothing may touch them or start another move.
 */
class VideoBaseFolderMoveRunningException extends ApiException implements ShouldntReport
{
    public function __construct()
    {
        parent::__construct(ApiErrorCode::VideoBaseFolderMoveRunning, 409, 'A video base folder move is running.');
    }
}
