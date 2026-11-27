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

    /**
     * @param  array<string, mixed>  $result  A search result as nekoBT sends it; its counts come as strings
     */
    public static function fromNekoBt(array $result): self
    {
        $baseUrl = config('nekobt.base_url');
        // Full subtitles (`sub_lang`) and fansubs on top of them (`fsub_lang`) are both subtitles to a viewer
        $subtitleLanguages = [
            ...self::splitLanguages($result['sub_lang'] ?? null),
            ...self::splitLanguages($result['fsub_lang'] ?? null),
        ];

        return new self(
            id: (string) $result['id'],
            title: $result['title'],
            sizeBytes: (int) ($result['filesize'] ?? 0),
            // It comes in milliseconds
            uploadedAt: CarbonImmutable::createFromTimestampUTC(intdiv((int) $result['uploaded_at'], 1000)),
            seeders: (int) ($result['seeders'] ?? 0),
            leechers: (int) ($result['leechers'] ?? 0),
            downloads: (int) ($result['completed'] ?? 0),
            comments: (int) ($result['comment_count'] ?? 0),
            isBatch: (bool) ($result['batch'] ?? false),
            uploader: self::getUploaderName($result),
            groups: self::getGroupNames($result),
            audioLanguages: self::splitLanguages($result['audio_lang'] ?? null),
            subtitleLanguages: array_values(array_unique($subtitleLanguages)),
            level: NekoBtLevel::fromNekoBtNumber($result['level'] ?? null),
            videoSource: NekoBtVideoSource::fromNekoBtNumber($result['video_type'] ?? null),
            videoCodec: NekoBtVideoCodec::fromNekoBtNumber($result['video_codec'] ?? null),
            isOriginalTranslation: (bool) ($result['otl'] ?? false),
            isMachineTranslation: (bool) ($result['mtl'] ?? false),
            isHardsub: (bool) ($result['hardsub'] ?? false),
            magnetUrl: $result['magnet'],
            torrentUrl: "{$baseUrl}/api/v1/torrents/{$result['id']}/download?public=true",
            viewUrl: "{$baseUrl}/torrents/{$result['id']}",
            mediaUrl: self::getMediaUrl($result),
        );
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private static function getUploaderName(array $result): ?string
    {
        $isAnonymous = (bool) ($result['anonymous'] ?? false);

        if ($isAnonymous) {
            return null;
        }

        return $result['uploader']['display_name'] ?? null;
    }

    /**
     * Uploads without groups often name theirs in the title instead, as `[Group] Title`.
     *
     * @param  array<string, mixed>  $result
     * @return list<string>
     */
    private static function getGroupNames(array $result): array
    {
        $groupNames = collect($result['groups'] ?? [])->pluck('display_name')->filter()->values()->all();

        if ($groupNames === [] && preg_match('/^\[([^\]]+)\]/', $result['title'], $matches)) {
            return [trim($matches[1])];
        }

        return $groupNames;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private static function getMediaUrl(array $result): ?string
    {
        if (!isset($result['media_id'])) {
            return null;
        }

        return config('nekobt.base_url')."/media/{$result['media_id']}";
    }

    /**
     * @param  string|null  $languages  Codes separated by commas, such as `ja,en`
     * @return list<string>
     */
    private static function splitLanguages(?string $languages): array
    {
        if ($languages === null || $languages === '') {
            return [];
        }

        return explode(',', $languages);
    }
}
