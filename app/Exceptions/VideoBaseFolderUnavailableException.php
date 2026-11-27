<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use Illuminate\Contracts\Debug\ShouldntReport;

/**
 * The video base folder's drive isn't there, so no file in it can be judged.
 */
class VideoBaseFolderUnavailableException extends ApiException implements ShouldntReport
{
    public function __construct()
    {
        parent::__construct(ApiErrorCode::VideoBaseFolderUnavailable, 409, 'The video base folder is not available.');
    }
}
