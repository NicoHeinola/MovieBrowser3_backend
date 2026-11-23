<?php

namespace App\Models\Download\Relations;

use App\Models\DownloadPick\DownloadPick;
use App\Models\Show\Show;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasDownloadRelations
{
    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    /** The files picked for episodes */
    public function picks(): HasMany
    {
        return $this->hasMany(DownloadPick::class)->orderBy('file_index');
    }
}
