<?php

namespace App\Models\Website\Relations;

use App\Models\WebsiteTag\WebsiteTag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @mixin Model */
trait HasWebsiteRelations
{
    public function tags(): HasMany
    {
        return $this->hasMany(WebsiteTag::class)->orderBy('name');
    }
}
