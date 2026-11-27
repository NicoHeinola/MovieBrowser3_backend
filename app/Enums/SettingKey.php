<?php

namespace App\Enums;

/**
 * The key of each row in `settings`. Seeded by migrations, so a new case needs a migration that inserts its row.
 */
enum SettingKey: string
{
    case BannerDefaultVideos = 'banner_default_videos';
    case BannerDefaultBackgrounds = 'banner_default_backgrounds';
    case VideoBaseFolder = 'video_base_folder';
    case VlcMediaPlayerPath = 'vlc_media_player_path';
    case SetupCompletedAt = 'setup_completed_at';
    case QBittorrentUrl = 'qbittorrent_url';
    case QBittorrentUsername = 'qbittorrent_username';
    case QBittorrentPassword = 'qbittorrent_password';
    case DownloadPath = 'download_path';
}
