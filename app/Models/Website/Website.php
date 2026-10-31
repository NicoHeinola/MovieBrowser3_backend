<?php

namespace App\Models\Website;

use App\Models\Website\Query\HasWebsiteQuery;
use App\Models\Website\Relations\HasWebsiteRelations;
use Database\Factories\WebsiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['url', 'title', 'icon', 'description'])]
class Website extends Model
{
    /** @use HasFactory<WebsiteFactory> */
    use HasFactory;

    use HasWebsiteQuery;
    use HasWebsiteRelations;

    protected static function newFactory(): WebsiteFactory
    {
        return WebsiteFactory::new();
    }
}
