<?php

namespace App\Models\VideoBaseFolderMove\Query;

use Illuminate\Database\Eloquent\Builder;

trait HasVideoBaseFolderMoveQuery
{
    /**
     * The folder move still moving files, forwards or back. A dead worker can't leave one behind: its job is retried
     * after `retry_after`, fails on the attempt limit and ends the folder move through `failed()`.
     */
    public function scopeRunning(Builder $query): void
    {
        $query->where('running', true);
    }
}
