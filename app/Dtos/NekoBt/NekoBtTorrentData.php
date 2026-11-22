<?php

namespace App\Dtos\NekoBt;

use App\Enums\NekoBtLevel;
use App\Enums\NekoBtVideoCodec;
use App\Enums\NekoBtVideoSource;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

class NekoBtTorrentData extends Data
{
    /**
     * One nekoBT torrent of a search result. The links point at nekoBT, except the magnet, which works in any
     * torrent client. Details the uploader left out are null or empty.
     *
     * @param  string  $id  nekoBT's id, kept as the string nekoBT sends
     * @param  string|null  $uploader  Who uploaded it, or null when anonymous
     * @param  list<string>  $groups  Release groups that made it
     * @param  list<string>  $audioLanguages  Language codes as nekoBT writes them, such as `ja` or `es-419`
     * @param  list<string>  $subtitleLanguages  Language codes as nekoBT writes them
     * @param  bool  $isOriginalTranslation  Translated by the group itself instead of reusing an official translation
     * @param  string|null  $mediaUrl  nekoBT's page of the show or movie the torrent belongs to, when it knows one
     */
    public function __construct(
        public string $id,
        public string $title,
        public int $sizeBytes,
        public CarbonImmutable $uploadedAt,
        public int $seeders,
        public int $leechers,
        public int $downloads,
        public int $comments,
        public bool $isBatch,
        public ?string $uploader,
        public array $groups,
        public array $audioLanguages,
        public array $subtitleLanguages,
        public ?NekoBtLevel $level,
        public ?NekoBtVideoSource $videoSource,
        public ?NekoBtVideoCodec $videoCodec,
        public bool $isOriginalTranslation,
        public bool $isMachineTranslation,
        public bool $isHardsub,
        public string $magnetUrl,
        public string $torrentUrl,
        public string $viewUrl,
        public ?string $mediaUrl,
    ) {}
}
