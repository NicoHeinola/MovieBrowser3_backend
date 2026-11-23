<?php

namespace App\Models\DownloadPick\Relations;

use App\Models\Download\Download;
use App\Models\Episode\Episode;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasDownloadPickRelations
{
    public function download(): BelongsTo
    {
        return $this->belongsTo(Download::class);
    }

    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }
}
