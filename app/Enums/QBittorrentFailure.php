<?php

namespace App\Enums;

/**
 * Why qBittorrent's Web API couldn't be used. The frontend turns each into translated text.
 */
enum QBittorrentFailure: string
{
    case Unreachable = 'unreachable';
    case LoginFailed = 'login_failed';
    /** qBittorrent bans an address for a while after too many failed logins */
    case Banned = 'banned';
    /** qBittorrent turned a torrent down, such as a link that isn't a torrent */
    case TorrentRejected = 'torrent_rejected';
}
