<?php

namespace App\Models\VideoFileCheck\Query;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

trait HasVideoFileCheckQuery
{
    /**
     * Checks still making progress.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('running', true)->where('updated_at', '>=', $this->staleBefore());
    }

    /**
     * Checks that never finished and stopped making progress: their worker died.
     */
    public function scopeStale(Builder $query): void
    {
        $query->where('running', true)->where('updated_at', '<', $this->staleBefore());
    }

    private function staleBefore(): CarbonInterface
    {
        return now()->subSeconds((int) config('video_files.check_stale_after_seconds'));
    }
}
