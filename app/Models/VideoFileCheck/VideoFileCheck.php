<?php

namespace App\Models\VideoFileCheck;

use App\Enums\VideoFileCheckErrorCode;
use App\Models\VideoFileCheck\Query\HasVideoFileCheckQuery;
use Database\Factories\VideoFileCheckFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One run of the check of every episode's video file. `updated_at` is its last progress.
 */
#[Fillable(['running', 'checked', 'total', 'error_code', 'finished_at'])]
class VideoFileCheck extends Model
{
    /** @use HasFactory<VideoFileCheckFactory> */
    use HasFactory;

    use HasVideoFileCheckQuery;

    protected $casts = [
        'running' => 'boolean',
        'checked' => 'integer',
        'total' => 'integer',
        'error_code' => VideoFileCheckErrorCode::class,
        'finished_at' => 'immutable_datetime',
    ];

    protected static function newFactory(): VideoFileCheckFactory
    {
        return VideoFileCheckFactory::new();
    }
}
