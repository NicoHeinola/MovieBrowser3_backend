<?php

namespace App\Exceptions;

use App\Enums\SetupCheckCode;

/**
 * A failed VLC bridge call. Playback shows the message; the setup check sends the code and params instead.
 */
class VlcBridgeException extends VlcLaunchException
{
    /**
     * @param  array<string, string>  $params
     */
    public function __construct(
        string $message,
        public readonly SetupCheckCode $checkCode,
        public readonly array $params = [],
    ) {
        parent::__construct($message);
    }
}
