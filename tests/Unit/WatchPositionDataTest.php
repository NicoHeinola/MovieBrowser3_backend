<?php

use App\Dtos\ShowWatchProgress\WatchPositionData;

beforeEach(fn () => config(['watch_progress.finished_before_end_seconds' => 120]));

test('a position counts as near the end only inside the last window of a longer video', function (?int $position, ?int $duration, bool $nearEnd) {
    expect((new WatchPositionData($position, $duration))->isNearEnd())->toBe($nearEnd);
})->with([
    'well before the window' => [1000, 1440, false],
    'one second before the window' => [1319, 1440, false],
    'exactly at the window' => [1320, 1440, true],
    'at the very end' => [1440, 1440, true],
    'unknown position' => [null, 1440, false],
    'unknown length' => [1400, null, false],
    'video as short as the window' => [100, 120, false],
    'video shorter than the window' => [50, 60, false],
]);
