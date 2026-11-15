<?php

namespace App\Enums;

/**
 * Why a database backup failed. The frontend turns each code into translated text.
 */
enum BackupErrorCode: string
{
    case DirUnavailable = 'backup_dir_unavailable';
    case Failed = 'backup_failed';
}
