<?php

namespace App\Models\Query;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Scopes for a background run kept as one row: `running` is true until it ends, and each bit of progress touches
 * `updated_at`. A dead worker leaves its row running, so a run that stops making progress counts as stale and a new
 * run may replace it.
 */
trait HasStaleRunScopes
{
    /**
     * How long a run may go without progress before it counts as dead. Keep it well above the time one chunk takes.
     */
    abstract protected function staleAfterSeconds(): int;

    /**
     * Runs still making progress.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('running', true)->where('updated_at', '>=', $this->staleBefore());
    }

    /**
     * Runs that never finished and stopped making progress: their worker died.
     */
    public function scopeStale(Builder $query): void
    {
        $query->where('running', true)->where('updated_at', '<', $this->staleBefore());
    }

    private function staleBefore(): CarbonInterface
    {
        return now()->subSeconds($this->staleAfterSeconds());
    }
}
