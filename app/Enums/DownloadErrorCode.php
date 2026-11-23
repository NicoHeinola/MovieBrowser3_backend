<?php

namespace App\Enums;

/**
 * Why a download failed. The frontend turns each code into translated text.
 */
enum DownloadErrorCode: string
{
    /** qBittorrent no longer has the torrent, such as after someone removed it there */
    case ClientLostTorrent = 'client_lost_torrent';
}
