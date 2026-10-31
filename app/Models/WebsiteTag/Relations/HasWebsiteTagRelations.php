<?php

namespace App\Models\WebsiteTag\Relations;

use App\Models\Website\Website;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasWebsiteTagRelations
{
    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
