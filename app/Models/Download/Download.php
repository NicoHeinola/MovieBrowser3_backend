<?php

namespace App\Models\Download;

use App\Enums\DownloadErrorCode;
use App\Enums\DownloadStatus;
use App\Models\Download\Relations\HasDownloadRelations;
use Database\Factories\DownloadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A torrent handed to qBittorrent. qBittorrent holds its progress; this row holds where its files go.
 */
#[Fillable(['hash', 'name', 'magnet_url', 'show_id', 'status', 'error_code'])]
class Download extends Model
{
    use HasDownloadRelations;

    /** @use HasFactory<DownloadFactory> */
    use HasFactory;

    protected $casts = [
        'status' => DownloadStatus::class,
        'error_code' => DownloadErrorCode::class,
    ];

    protected static function newFactory(): DownloadFactory
    {
        return DownloadFactory::new();
    }
}
