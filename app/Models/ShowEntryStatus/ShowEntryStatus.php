<?php

namespace App\Models\ShowEntryStatus;

use App\Enums\ShowWatchStatus;
use App\Models\ShowEntryStatus\Relations\HasShowEntryStatusRelations;
use Database\Factories\ShowEntryStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['show_entry_id', 'user_id', 'status'])]
class ShowEntryStatus extends Model
{
    /** @use HasFactory<ShowEntryStatusFactory> */
    use HasFactory;

    use HasShowEntryStatusRelations;

    protected $casts = [
        'status' => ShowWatchStatus::class,
    ];

    protected static function newFactory(): ShowEntryStatusFactory
    {
        return ShowEntryStatusFactory::new();
    }
}
