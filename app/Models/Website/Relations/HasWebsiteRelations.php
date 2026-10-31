<?php

namespace App\Models\Website\Relations;

use App\Models\WebsiteTag\WebsiteTag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** @mixin Model */
trait HasWebsiteRelations
{
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(WebsiteTag::class)->withTimestamps()->orderByRaw('lower(website_tags.name)');
    }
}
