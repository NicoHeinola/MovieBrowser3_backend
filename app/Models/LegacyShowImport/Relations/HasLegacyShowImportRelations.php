<?php

namespace App\Models\LegacyShowImport\Relations;

use App\Models\Show\Show;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasLegacyShowImportRelations
{
    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }
}
