<?php

namespace App\Models\VideoDurationScan;

use App\Enums\VideoDurationScanErrorCode;
use App\Models\VideoDurationScan\Query\HasVideoDurationScanQuery;
use Database\Factories\VideoDurationScanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One run of reading the lengths of the episodes queued for it (`episodes.video_duration_queued_at`). Episodes queued
 * while it runs join it. `updated_at` is its last progress.
 */
#[Fillable(['running', 'read', 'total', 'error_code', 'finished_at'])]
class VideoDurationScan extends Model
{
    /** @use HasFactory<VideoDurationScanFactory> */
    use HasFactory;

    use HasVideoDurationScanQuery;

    protected $casts = [
        'running' => 'boolean',
        'read' => 'integer',
        'total' => 'integer',
        'error_code' => VideoDurationScanErrorCode::class,
        'finished_at' => 'immutable_datetime',
    ];

    protected static function newFactory(): VideoDurationScanFactory
    {
        return VideoDurationScanFactory::new();
    }
}
