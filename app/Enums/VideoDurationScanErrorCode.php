<?php

namespace App\Enums;

/**
 * What went wrong in an episode length scan. The frontend turns each code into translated text.
 */
enum VideoDurationScanErrorCode: string
{
    case VideoBasePathUnavailable = 'video_base_path_unavailable';
    case Failed = 'video_duration_scan_failed';
}
