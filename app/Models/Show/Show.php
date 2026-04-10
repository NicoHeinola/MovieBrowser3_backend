<?php

namespace App\Models\Show;

use App\Models\Show\Query\HasShowQuery;
use App\Models\Show\Relations\HasShowRelations;
use Database\Factories\ShowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['banner_url', 'card_image_url', 'preview_url', 'description'])]
class Show extends Model
{
    /** @use HasFactory<ShowFactory> */
    use HasFactory;

    use HasShowQuery;
    use HasShowRelations;

    protected static function newFactory(): ShowFactory
    {
        return ShowFactory::new();
    }
}
