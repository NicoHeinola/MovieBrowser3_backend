<?php

namespace App\Enums;

/**
 * Why a video file check ended early. The frontend turns each code into translated text.
 */
enum VideoFileCheckErrorCode: string
{
    case VideoBasePathUnavailable = 'video_base_path_unavailable';
    case Failed = 'video_file_check_failed';
}
