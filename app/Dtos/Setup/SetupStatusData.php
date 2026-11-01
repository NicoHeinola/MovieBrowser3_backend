<?php

namespace App\Dtos\Setup;

use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

/**
 * What the first-login setup wizard needs: whether setup is done, the media folders the installer mounted, and a VLC
 * it found.
 */
class SetupStatusData extends Data
{
    /**
     * @param  list<string>  $mediaFolders
     */
    public function __construct(
        public ?Carbon $completedAt,
        public array $mediaFolders,
        public ?string $detectedVlcMediaPlayerPath,
    ) {}
}
