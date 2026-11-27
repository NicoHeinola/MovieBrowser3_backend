<?php

namespace App\Enums;

/**
 * How a video base folder move failed. The frontend turns each code into translated text.
 */
enum VideoBaseFolderMoveErrorCode: string
{
    // The moved files went back, so the old folder still holds everything
    case Failed = 'video_base_folder_move_failed';
    // Moving them back failed too, so files may sit in either folder
    case RevertFailed = 'video_base_folder_move_revert_failed';
}
