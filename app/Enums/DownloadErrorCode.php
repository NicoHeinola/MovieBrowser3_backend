<?php

namespace App\Enums;

/**
 * Why a download failed. The frontend turns each code into translated text.
 */
enum DownloadErrorCode: string
{
    /** qBittorrent no longer has the torrent, such as after someone removed it there */
    case ClientLostTorrent = 'client_lost_torrent';
    /** A finished file isn't in the download folder, such as after someone moved it */
    case FileMissing = 'file_missing';
    /** The video disk has too little room for a finished file */
    case NotEnoughDiskSpace = 'not_enough_disk_space';
    /** Copying a finished file into the video base folder failed; the log says why */
    case ImportFailed = 'import_failed';
}
