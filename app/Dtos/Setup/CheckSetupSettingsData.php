<?php

namespace App\Dtos\Setup;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * The setup values to check. Only the values that were sent are checked.
 */
class CheckSetupSettingsData extends Data
{
    public function __construct(
        public string|Optional|null $videoBasePath,
        public string|Optional|null $vlcMediaPlayerPath,
    ) {}
}
