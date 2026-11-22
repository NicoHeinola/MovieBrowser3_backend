<?php

namespace App\Exceptions;

use App\Enums\SetupCheckCode;
use App\Enums\VlcLaunchCode;
use App\Http\Resources\ApiError\ApiErrorDetail;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * VLC could not be opened. The message is for the logs; the response carries the code and params.
 */
class VlcLaunchException extends RuntimeException
{
    /**
     * @param  array<string, string>  $params
     */
    public function __construct(
        string $message,
        public readonly VlcLaunchCode|SetupCheckCode $errorCode,
        public readonly array $params = [],
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return ApiErrorDetail::response($this->errorCode, 503, $this->params);
    }
}
