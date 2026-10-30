<?php

namespace App\Models\ShowEntryWatchStatus;

use App\Enums\WatchStatus;
use App\Models\ShowEntryWatchStatus\Relations\HasShowEntryWatchStatusRelations;
use Database\Factories\ShowEntryWatchStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['show_entry_id', 'user_id', 'status'])]
class ShowEntryWatchStatus extends Model
{
    /** @use HasFactory<ShowEntryWatchStatusFactory> */
    use HasFactory;

    use HasShowEntryWatchStatusRelations;

    protected $casts = [
        'status' => WatchStatus::class,
    ];

    protected static function newFactory(): ShowEntryWatchStatusFactory
    {
        return ShowEntryWatchStatusFactory::new();
    }
}
