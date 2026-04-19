<?php

namespace App\Models\ShowLink;

use App\Enums\ShowLinkType;
use App\Models\ShowLink\Query\HasShowLinkQuery;
use App\Models\ShowLink\Relations\HasShowLinkRelations;
use Database\Factories\ShowLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['source_show_id', 'target_show_id', 'type'])]
class ShowLink extends Model
{
    /** @use HasFactory<ShowLinkFactory> */
    use HasFactory;

    use HasShowLinkQuery;
    use HasShowLinkRelations;

    protected $casts = [
        'type' => ShowLinkType::class,
    ];

    protected static function newFactory(): ShowLinkFactory
    {
        return ShowLinkFactory::new();
    }
}
