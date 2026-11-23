<?php

namespace App\Models\Episode\Relations;

use App\Models\DownloadPick\DownloadPick;
use App\Models\ShowEntry\ShowEntry;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

trait HasEpisodeRelations
{
    public function entry(): BelongsTo
    {
        return $this->belongsTo(ShowEntry::class, 'show_entry_id');
    }

    /** The download file this episode waits for, if any */
    public function downloadPick(): HasOne
    {
        return $this->hasOne(DownloadPick::class);
    }
}
