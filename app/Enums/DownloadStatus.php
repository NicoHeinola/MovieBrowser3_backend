<?php

namespace App\Enums;

/**
 * Where a download stands. `FetchingMetadata` is only reported, never stored: it's a waiting download whose torrent
 * qBittorrent hasn't described yet.
 */
enum DownloadStatus: string
{
    case FetchingMetadata = 'fetching_metadata';
    /** Added to qBittorrent, stopped, until files are picked for episodes */
    case WaitingForFiles = 'waiting_for_files';
    case Downloading = 'downloading';
    case Importing = 'importing';
    case Completed = 'completed';
    case Failed = 'failed';
}
