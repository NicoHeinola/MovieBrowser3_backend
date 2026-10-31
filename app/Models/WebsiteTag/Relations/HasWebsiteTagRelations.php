<?php

namespace App\Models\WebsiteTag\Relations;

use App\Models\Website\Website;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** @mixin Model */
trait HasWebsiteTagRelations
{
    public function websites(): BelongsToMany
    {
        return $this->belongsToMany(Website::class)->withTimestamps();
    }
}
