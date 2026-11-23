<?php

namespace App\Models\DownloadPick;

use App\Models\DownloadPick\Relations\HasDownloadPickRelations;
use Database\Factories\DownloadPickFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A file of a download that becomes an episode's video file once it's downloaded.
 */
#[Fillable(['download_id', 'file_index', 'path', 'size_bytes', 'episode_id', 'imported_at'])]
class DownloadPick extends Model
{
    use HasDownloadPickRelations;

    /** @use HasFactory<DownloadPickFactory> */
    use HasFactory;

    protected $casts = [
        'file_index' => 'integer',
        'size_bytes' => 'integer',
        'imported_at' => 'immutable_datetime',
    ];

    protected static function newFactory(): DownloadPickFactory
    {
        return DownloadPickFactory::new();
    }
}
