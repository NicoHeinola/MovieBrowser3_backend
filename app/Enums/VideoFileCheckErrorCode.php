<?php

namespace App\Enums;

/**
 * What went wrong in a video file check. The frontend turns each code into translated text.
 */
enum VideoFileCheckErrorCode: string
{
    case VideoBasePathUnavailable = 'video_base_path_unavailable';
    case Failed = 'video_file_check_failed';
}
