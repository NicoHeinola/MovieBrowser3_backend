<?php

namespace App\Models\ShowSection;

use App\Models\ShowSection\Query\HasShowSectionQuery;
use App\Models\ShowSection\Relations\HasShowSectionRelations;
use Database\Factories\ShowSectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['category_id', 'description', 'sort_order'])]
class ShowSection extends Model
{
    /** @use HasFactory<ShowSectionFactory> */
    use HasFactory;

    use HasShowSectionQuery;
    use HasShowSectionRelations;

    protected static function newFactory(): ShowSectionFactory
    {
        return ShowSectionFactory::new();
    }
}
