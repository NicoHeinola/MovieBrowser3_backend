<?php

namespace App\Models\ShowWatchProgress;

use App\Models\ShowWatchProgress\Relations\HasShowWatchProgressRelations;
use Database\Factories\ShowWatchProgressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['show_id', 'user_id', 'show_entry_id', 'episode_id', 'position_seconds', 'duration_seconds'])]
class ShowWatchProgress extends Model
{
    /** @use HasFactory<ShowWatchProgressFactory> */
    use HasFactory;

    use HasShowWatchProgressRelations;

    protected static function newFactory(): ShowWatchProgressFactory
    {
        return ShowWatchProgressFactory::new();
    }
}
