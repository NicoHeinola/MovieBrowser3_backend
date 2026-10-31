<?php

namespace App\Models\WebsiteTag;

use App\Models\WebsiteTag\Query\HasWebsiteTagQuery;
use App\Models\WebsiteTag\Relations\HasWebsiteTagRelations;
use Database\Factories\WebsiteTagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name'])]
class WebsiteTag extends Model
{
    /** @use HasFactory<WebsiteTagFactory> */
    use HasFactory;

    use HasWebsiteTagQuery;
    use HasWebsiteTagRelations;

    protected static function newFactory(): WebsiteTagFactory
    {
        return WebsiteTagFactory::new();
    }
}
