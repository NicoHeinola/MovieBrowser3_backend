<?php

namespace App\Models\VideoFileCheck\Query;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * A dead worker leaves its check running, so one that stops making progress counts as stale and a new one may replace
 * it.
 */
trait HasVideoFileCheckQuery
{
    /**
     * Checks still making progress.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('running', true)->where('updated_at', '>=', $this->getStaleBefore());
    }

    /**
     * Checks that never finished and stopped making progress: their worker died.
     */
    public function scopeStale(Builder $query): void
    {
        $query->where('running', true)->where('updated_at', '<', $this->getStaleBefore());
    }

    private function getStaleBefore(): CarbonInterface
    {
        return now()->subSeconds((int) config('video_files.check_stale_after_seconds'));
    }
}
