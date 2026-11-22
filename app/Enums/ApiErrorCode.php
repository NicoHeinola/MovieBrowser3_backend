<?php

namespace App\Enums;

/**
 * Why a request was turned away before it reached a controller. The frontend turns each code into translated text.
 */
enum ApiErrorCode: string
{
    case Unauthenticated = 'unauthenticated';
    case Forbidden = 'forbidden';
    case VideoRelocationRunning = 'video_relocation_running';
    case VideoFileCheckRunning = 'video_file_check_running';
    case VideoDurationScanRunning = 'video_duration_scan_running';
}
