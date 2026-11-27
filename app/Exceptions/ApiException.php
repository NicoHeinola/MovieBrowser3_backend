<?php

namespace App\Exceptions;

use App\Http\Resources\ApiError\ApiErrorDetail;
use BackedEnum;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * An error the frontend shows: it answers with a code and params, which the frontend turns into translated text.
 */
abstract class ApiException extends RuntimeException
{
    /**
     * @param  int  $status  The response's HTTP status
     * @param  string  $message  For the logs only
     * @param  array<string, string>  $params  What the frontend puts into the code's text
     */
    public function __construct(
        public readonly BackedEnum $errorCode,
        private readonly int $status,
        string $message,
        public readonly array $params = [],
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return ApiErrorDetail::response($this->errorCode, $this->status, $this->params);
    }
}
