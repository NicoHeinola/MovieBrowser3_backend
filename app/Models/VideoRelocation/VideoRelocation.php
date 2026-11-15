<?php

namespace App\Models\VideoRelocation;

use App\Enums\VideoRelocationErrorCode;
use App\Models\VideoRelocation\Query\HasVideoRelocationQuery;
use Database\Factories\VideoRelocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One move of every video file from one base folder to another. The `video_base_path` setting changes only once it
 * succeeds.
 */
#[Fillable(['running', 'reverting', 'from_path', 'to_path', 'moved', 'total', 'error_code', 'error_path', 'finished_at'])]
class VideoRelocation extends Model
{
    /** @use HasFactory<VideoRelocationFactory> */
    use HasFactory;

    use HasVideoRelocationQuery;

    protected $casts = [
        'running' => 'boolean',
        'reverting' => 'boolean',
        'moved' => 'integer',
        'total' => 'integer',
        'error_code' => VideoRelocationErrorCode::class,
        'finished_at' => 'immutable_datetime',
    ];

    protected static function newFactory(): VideoRelocationFactory
    {
        return VideoRelocationFactory::new();
    }
}
