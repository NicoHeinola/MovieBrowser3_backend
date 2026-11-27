<?php

namespace App\Enums;

/**
 * Why a setup value failed its check. The frontend turns each code into translated text.
 */
enum SetupCheckCode: string
{
    case PathRequired = 'path_required';
    case PathNotAbsolute = 'path_not_absolute';
    case PathHasParentSegment = 'path_has_parent_segment';
    case PathOutsideMediaFolders = 'path_outside_media_folders';
    case PathIsFile = 'path_is_file';
    case PathNotWritable = 'path_not_writable';
    case PathNotCreatable = 'path_not_creatable';
    case VlcNotFound = 'vlc_not_found';
    case VlcWrongBinary = 'vlc_wrong_binary';
    case HostBridgeUnreachable = 'host_bridge_unreachable';
    case HostBridgeOutdated = 'host_bridge_outdated';
    case HostBridgeUnauthorized = 'host_bridge_unauthorized';
    case HostBridgeRejected = 'host_bridge_rejected';
    case UrlRequired = 'url_required';
    case QBittorrentUnreachable = 'qbittorrent_unreachable';
    case QBittorrentLoginFailed = 'qbittorrent_login_failed';
    case QBittorrentBanned = 'qbittorrent_banned';
    case QBittorrentOutdated = 'qbittorrent_outdated';
}
