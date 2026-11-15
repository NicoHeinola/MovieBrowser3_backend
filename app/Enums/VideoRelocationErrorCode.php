<?php

namespace App\Enums;

/**
 * How a video folder relocation failed. The frontend turns each code into translated text.
 */
enum VideoRelocationErrorCode: string
{
    // The moved files went back, so the old folder still holds everything
    case Failed = 'video_relocation_failed';
    // Moving them back failed too, so files may sit in either folder
    case RevertFailed = 'video_relocation_revert_failed';
}
