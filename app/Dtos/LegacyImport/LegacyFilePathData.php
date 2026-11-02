<?php

namespace App\Dtos\LegacyImport;

use Spatie\LaravelData\Data;

/**
 * A MovieBrowser2 path: relative to its video folder, and absolute as MovieBrowser2 saw it.
 */
class LegacyFilePathData extends Data
{
    public function __construct(
        public string $relative,
        public string $absolute,
    ) {}
}
