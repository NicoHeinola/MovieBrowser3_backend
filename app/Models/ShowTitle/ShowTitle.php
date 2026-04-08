<?php

namespace App\Models\ShowTitle;

use App\Models\ShowTitle\Query\HasShowTitleQuery;
use App\Models\ShowTitle\Relations\HasShowTitleRelations;
use Database\Factories\ShowTitleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['show_id', 'title', 'is_primary'])]
class ShowTitle extends Model
{
    /** @use HasFactory<ShowTitleFactory> */
    use HasFactory;

    use HasShowTitleQuery;
    use HasShowTitleRelations;

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    protected static function newFactory(): ShowTitleFactory
    {
        return ShowTitleFactory::new();
    }
}
