<?php

namespace App\Enums;

/**
 * Why a request was turned away or could not be answered. The frontend turns each code into translated text.
 */
enum ApiErrorCode: string
{
    case Unauthenticated = 'unauthenticated';
    case Forbidden = 'forbidden';
    case VideoRelocationRunning = 'video_relocation_running';
    case VideoFileCheckRunning = 'video_file_check_running';
    case VideoDurationScanRunning = 'video_duration_scan_running';
    case TorrentSiteUnavailable = 'torrent_site_unavailable';
    case ShowTemplateNotFound = 'show_template_not_found';
    case ShowTemplateUnavailable = 'show_template_unavailable';
    case MalUnavailable = 'mal_unavailable';
    case QBittorrentUnavailable = 'qbittorrent_unavailable';
    case DownloadClientNotConfigured = 'download_client_not_configured';
    case DownloadExists = 'download_exists';
    case DownloadNotInClient = 'download_not_in_client';
}
