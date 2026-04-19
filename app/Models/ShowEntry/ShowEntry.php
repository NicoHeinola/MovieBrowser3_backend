<?php

namespace App\Models\ShowEntry;

use App\Enums\ShowEntryType;
use App\Models\ShowEntry\Query\HasShowEntryQuery;
use App\Models\ShowEntry\Relations\HasShowEntryRelations;
use Database\Factories\ShowEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['show_id', 'type', 'name', 'sort_order'])]
class ShowEntry extends Model
{
    /** @use HasFactory<ShowEntryFactory> */
    use HasFactory;

    use HasShowEntryQuery;
    use HasShowEntryRelations;

    protected $casts = [
        'type' => ShowEntryType::class,
    ];

    protected static function newFactory(): ShowEntryFactory
    {
        return ShowEntryFactory::new();
    }
}
