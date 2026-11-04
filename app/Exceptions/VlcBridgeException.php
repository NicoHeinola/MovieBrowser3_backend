<?php

namespace App\Exceptions;

use App\Enums\SetupCheckCode;

/**
 * A failed VLC bridge call. Its code is always a setup check code, so the setup check can report it as well.
 */
class VlcBridgeException extends VlcLaunchException
{
    /**
     * @param  array<string, string>  $params
     */
    public function __construct(
        string $message,
        public readonly SetupCheckCode $checkCode,
        array $params = [],
    ) {
        parent::__construct($message, $checkCode, $params);
    }
}
