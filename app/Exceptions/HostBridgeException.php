<?php

namespace App\Exceptions;

use App\Enums\SetupCheckCode;

/**
 * A failed host bridge call. Its code is always a setup check code, so the setup check can report it as well. The message
 * is for the logs.
 */
class HostBridgeException extends ApiException
{
    /**
     * @param  array<string, string>  $params
     */
    public function __construct(
        string $message,
        SetupCheckCode $errorCode,
        array $params = [],
    ) {
        parent::__construct($errorCode, 503, $message, $params);
    }
}
