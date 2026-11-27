<?php

namespace App\Dtos\EpisodePlayback;

use App\Dtos\ShowWatchProgress\WatchPositionData;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

class VlcStatusData extends Data
{
    /**
     * What VLC is doing, as its web interface reports it.
     *
     * @param  string|null  $filename  The playing file's name, without its folder; null while VLC has none open
     * @param  int|null  $durationSeconds  Null while VLC doesn't know it yet
     */
    public function __construct(
        public string $state = '',
        #[MapInputName('information.category.meta.filename')]
        public ?string $filename = null,
        #[MapInputName('time')]
        public int $positionSeconds = 0,
        #[MapInputName('length')]
        public ?int $durationSeconds = null,
    ) {}

    public function isPlaying(): bool
    {
        return $this->state === 'playing';
    }

    /**
     * Changes VLC's "not known" length (0 or less) to null.
     *
     * @param  array<string, mixed>  $properties  VLC's status, before the names are mapped
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $properties): array
    {
        if (($properties['length'] ?? 0) <= 0) {
            $properties['length'] = null;
        }

        return $properties;
    }

    public function getPosition(): WatchPositionData
    {
        return new WatchPositionData($this->positionSeconds, $this->durationSeconds);
    }
}
