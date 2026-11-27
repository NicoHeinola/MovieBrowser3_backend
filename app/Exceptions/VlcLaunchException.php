<?php

namespace App\Exceptions;

use App\Enums\VlcLaunchCode;

/**
 * VLC could not be opened. The message is for the logs; the response carries the code and params.
 */
class VlcLaunchException extends ApiException
{
    /**
     * @param  array<string, string>  $params
     */
    public function __construct(
        string $message,
        VlcLaunchCode $errorCode,
        array $params = [],
    ) {
        parent::__construct($errorCode, 503, $message, $params);
    }
}
