<?php

namespace App\Models\VideoRelocation\Query;

use Illuminate\Database\Eloquent\Builder;

trait HasVideoRelocationQuery
{
    /**
     * The relocation still moving files, forwards or back. A dead worker can't leave one behind: its job is retried
     * after `retry_after`, fails on the attempt limit and ends the relocation through `failed()`.
     */
    public function scopeRunning(Builder $query): void
    {
        $query->where('running', true);
    }
}
