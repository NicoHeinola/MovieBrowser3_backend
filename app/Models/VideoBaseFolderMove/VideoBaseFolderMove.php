<?php

namespace App\Models\VideoBaseFolderMove;

use App\Enums\VideoBaseFolderMoveErrorCode;
use App\Models\VideoBaseFolderMove\Query\HasVideoBaseFolderMoveQuery;
use Database\Factories\VideoBaseFolderMoveFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One move of every video file from one video base folder to another. The `video_base_folder` setting changes only once
 * it succeeds.
 */
#[Fillable(['running', 'reverting', 'from_path', 'to_path', 'moved', 'total', 'error_code', 'error_path', 'finished_at'])]
class VideoBaseFolderMove extends Model
{
    /** @use HasFactory<VideoBaseFolderMoveFactory> */
    use HasFactory;

    use HasVideoBaseFolderMoveQuery;

    protected $casts = [
        'running' => 'boolean',
        'reverting' => 'boolean',
        'moved' => 'integer',
        'total' => 'integer',
        'error_code' => VideoBaseFolderMoveErrorCode::class,
        'finished_at' => 'immutable_datetime',
    ];

    protected static function newFactory(): VideoBaseFolderMoveFactory
    {
        return VideoBaseFolderMoveFactory::new();
    }

    /**
     * Where the videos move from now: the old folder, or the new one while they move back after a failure.
     */
    public function getSourceFolder(): string
    {
        if ($this->reverting) {
            return $this->to_path;
        }

        return $this->from_path;
    }

    /**
     * Where the videos move to now: the new folder, or the old one while they move back after a failure.
     */
    public function getTargetFolder(): string
    {
        if ($this->reverting) {
            return $this->from_path;
        }

        return $this->to_path;
    }
}
