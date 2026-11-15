<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A video file check is running, so nothing may start another check or move the files it is looking at.
 */
class VideoFileCheckRunningException extends RuntimeException implements ShouldntReport
{
    public function __construct()
    {
        parent::__construct('A video file check is running.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['code' => ApiErrorCode::VideoFileCheckRunning, 'params' => (object) []], 409);
    }
}
