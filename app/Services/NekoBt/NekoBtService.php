<?php

namespace App\Services\NekoBt;

use App\Dtos\NekoBt\NekoBtSearchData;
use App\Dtos\NekoBt\NekoBtTorrentData;
use App\Dtos\NekoBt\NekoBtTorrentPageData;
use App\Enums\NekoBtLevel;
use App\Enums\NekoBtSort;
use App\Enums\NekoBtVideoCodec;
use App\Enums\NekoBtVideoSource;
use App\Exceptions\TorrentSiteUnavailableException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Searches nekoBT, an anime torrent tracker, through its public JSON API.
 */
class NekoBtService
{
    /** Results per page: the API's own default, and few enough rows to show at once */
    public const PER_PAGE = 50;

    /** nekoBT's `level` numbers */
    private const LEVELS = [
        0 => NekoBtLevel::Official,
        1 => NekoBtLevel::SlightModifications,
        2 => NekoBtLevel::SmallScaleFansub,
        3 => NekoBtLevel::FullScaleFansub,
    ];

    /** nekoBT's `video_type` numbers */
    private const VIDEO_SOURCES = [
        0 => NekoBtVideoSource::Other,
        1 => NekoBtVideoSource::Vhs,
        2 => NekoBtVideoSource::LaserDisc,
        3 => NekoBtVideoSource::TvEncode,
        4 => NekoBtVideoSource::TvRaw,
        5 => NekoBtVideoSource::DvdRemux,
        6 => NekoBtVideoSource::DvdEncode,
        7 => NekoBtVideoSource::WebMini,
        8 => NekoBtVideoSource::WebEncode,
        9 => NekoBtVideoSource::WebDl,
        11 => NekoBtVideoSource::BdDisc,
        12 => NekoBtVideoSource::BdMini,
        13 => NekoBtVideoSource::BdEncode,
        14 => NekoBtVideoSource::BdRemux,
        15 => NekoBtVideoSource::Hybrid,
        16 => NekoBtVideoSource::DvdDisc,
    ];

    /** nekoBT's `video_codec` numbers */
    private const VIDEO_CODECS = [
        0 => NekoBtVideoCodec::Other,
        1 => NekoBtVideoCodec::H264,
        2 => NekoBtVideoCodec::H265,
        3 => NekoBtVideoCodec::Av1,
        4 => NekoBtVideoCodec::Vp9,
        5 => NekoBtVideoCodec::Mpeg2,
        6 => NekoBtVideoCodec::Xvid,
        7 => NekoBtVideoCodec::Wmv,
        8 => NekoBtVideoCodec::Vc1,
    ];

    /**
     * One page of torrents matching the search.
     *
     * @throws TorrentSiteUnavailableException When nekoBT can't be reached or answers with an error
     */
    public function getTorrents(NekoBtSearchData $search): NekoBtTorrentPageData
    {
        $data = $this->request('/api/v1/torrents/search', [
            'query' => $search->search ?? '',
            'sort_by' => $this->sortBy($search),
            'limit' => self::PER_PAGE,
            'offset' => ($search->page - 1) * self::PER_PAGE,
            ...$this->filters($search),
        ]);

        // Hidden and deleted uploads still come back in some results, and private ones have only a private magnet:
        // none of them has a public magnet to download
        $results = collect($data['results'])
            ->reject(fn (mixed $result): bool => is_array($result) && (($result['hidden'] ?? false)
                || ($result['deleted'] ?? null) !== null
                || ($result['magnet'] ?? null) === null));

        // A result missing what every torrent has is a change on nekoBT's side; skip it, and say so in the log
        [$torrents, $unreadable] = $results->partition(fn (mixed $result): bool => $this->isReadable($result));

        if ($unreadable->isNotEmpty()) {
            Log::warning("nekoBT sent {$unreadable->count()} results without an id, title, magnet or upload time");
        }

        // Past the deepest page the API allows there is no next page, whatever nekoBT says
        $hasMore = (bool) ($data['more'] ?? false) && $search->page < NekoBtSearchData::MAX_PAGE;

        return new NekoBtTorrentPageData(
            $torrents->map(fn (array $result): NekoBtTorrentData => $this->torrent($result))->values()->all(),
            $search->page,
            self::PER_PAGE,
            $hasMore,
        );
    }

    /**
     * nekoBT's filter params for the filters that are set: lists of its own numbers joined by commas, where any value
     * matches, and yes-or-no filters as the text `true` or `false`, the only form it takes.
     *
     * @return array<string, string>
     */
    private function filters(NekoBtSearchData $search): array
    {
        $numbers = fn (array $values, array $map): string => implode(',', array_map(
            fn (mixed $value): int => array_search($value, $map, true),
            $values,
        ));
        $yesOrNo = fn (?bool $value): ?string => $value === null ? null : ($value ? 'true' : 'false');

        $filters = [
            'batch' => $yesOrNo($search->batch),
            'levels' => $numbers($search->levels, self::LEVELS),
            'video_type' => $numbers($search->videoSources, self::VIDEO_SOURCES),
            'video_codec' => $numbers($search->videoCodecs, self::VIDEO_CODECS),
            'audio_lang' => $search->audioLanguage,
            'otl' => $yesOrNo($search->originalTranslation),
            'mtl' => $yesOrNo($search->machineTranslation),
            'hardsub' => $yesOrNo($search->hardsub),
        ];

        // Filters that aren't set stay out of the query, so nekoBT doesn't filter on them
        return array_filter($filters, fn (?string $value): bool => $value !== null && $value !== '');
    }

    /**
     * nekoBT's `sort_by`: the field name descending, with `_asc` for ascending. Its newest-first and oldest-first are
     * named `latest` and `oldest`, and its size field `filesize`.
     */
    private function sortBy(NekoBtSearchData $search): string
    {
        return match ($search->sort) {
            NekoBtSort::Best => 'best',
            NekoBtSort::Date => $search->descending ? 'latest' : 'oldest',
            default => ($search->sort === NekoBtSort::Size ? 'filesize' : $search->sort->value).($search->descending ? '' : '_asc'),
        };
    }

    /**
     * Whether a result has the parts every torrent needs; the rest have defaults.
     */
    private function isReadable(mixed $result): bool
    {
        return is_array($result)
            && is_scalar($result['id'] ?? null)
            && is_string($result['title'] ?? null)
            && is_string($result['magnet'] ?? null)
            && is_numeric($result['uploaded_at'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $result  One readable entry of the API's `results`; its counts come as strings
     */
    private function torrent(array $result): NekoBtTorrentData
    {
        $baseUrl = config('nekobt.base_url');

        return new NekoBtTorrentData(
            id: (string) $result['id'],
            title: $result['title'],
            sizeBytes: (int) ($result['filesize'] ?? 0),
            // Milliseconds dropped: nobody needs them, and a cached result keeps only whole seconds
            uploadedAt: CarbonImmutable::createFromTimestampUTC(intdiv((int) $result['uploaded_at'], 1000)),
            seeders: (int) ($result['seeders'] ?? 0),
            leechers: (int) ($result['leechers'] ?? 0),
            downloads: (int) ($result['completed'] ?? 0),
            comments: (int) ($result['comment_count'] ?? 0),
            isBatch: (bool) ($result['batch'] ?? false),
            uploader: !($result['anonymous'] ?? false) && is_string($result['uploader']['display_name'] ?? null)
                ? $result['uploader']['display_name']
                : null,
            groups: $this->groups($result),
            audioLanguages: $this->languages($result['audio_lang'] ?? null),
            // Full subtitles (`sub_lang`) and fansubs on top of them (`fsub_lang`) are both subtitles to a viewer
            subtitleLanguages: array_values(array_unique([
                ...$this->languages($result['sub_lang'] ?? null),
                ...$this->languages($result['fsub_lang'] ?? null),
            ])),
            level: self::LEVELS[$result['level'] ?? -1] ?? null,
            videoSource: self::VIDEO_SOURCES[$result['video_type'] ?? -1] ?? null,
            videoCodec: self::VIDEO_CODECS[$result['video_codec'] ?? -1] ?? null,
            isOriginalTranslation: (bool) ($result['otl'] ?? false),
            isMachineTranslation: (bool) ($result['mtl'] ?? false),
            isHardsub: (bool) ($result['hardsub'] ?? false),
            magnetUrl: $result['magnet'],
            torrentUrl: "{$baseUrl}/api/v1/torrents/{$result['id']}/download?public=true",
            viewUrl: "{$baseUrl}/torrents/{$result['id']}",
            mediaUrl: isset($result['media_id']) ? "{$baseUrl}/media/{$result['media_id']}" : null,
        );
    }

    /**
     * The release groups' names. Uploads without groups often name theirs in the title instead, as `[Group] Title`.
     *
     * @param  array<string, mixed>  $result
     * @return list<string>
     */
    private function groups(array $result): array
    {
        $names = collect(is_array($result['groups'] ?? null) ? $result['groups'] : [])
            ->pluck('display_name')
            ->filter(fn (mixed $name): bool => is_string($name) && $name !== '')
            ->values()
            ->all();

        if ($names === [] && preg_match('/^\[([^\]]+)\]/', $result['title'], $matches)) {
            return [trim($matches[1])];
        }

        return $names;
    }

    /**
     * @param  mixed  $codes  Comma-separated language codes, such as `ja,en`; anything else has none
     * @return list<string>
     */
    private function languages(mixed $codes): array
    {
        return is_string($codes) ? array_values(array_filter(explode(',', $codes))) : [];
    }

    /**
     * The `data` of an API answer. The API wraps every answer in `{ error, data }` and reports bad input as
     * `{ error: true, message }`.
     *
     * @param  array<string, string|int>  $query
     * @return array<string, mixed>
     *
     * @throws TorrentSiteUnavailableException
     */
    private function request(string $path, array $query): array
    {
        $url = config('nekobt.base_url').$path;

        try {
            $response = Http::acceptJson()->timeout(config('nekobt.timeout_seconds'))->get($url, $query);
        } catch (ConnectionException $exception) {
            Log::warning("nekoBT did not answer: {$exception->getMessage()}");

            throw new TorrentSiteUnavailableException('nekobt');
        }

        if ($response->failed() || $response->json('error') !== false || !is_array($response->json('data.results'))) {
            Log::warning("nekoBT answered {$response->status()}: {$this->errorMessage($response)}");

            throw new TorrentSiteUnavailableException('nekobt');
        }

        return $response->json('data');
    }

    private function errorMessage(Response $response): string
    {
        return (string) ($response->json('message') ?? mb_substr($response->body(), 0, 200));
    }
}
