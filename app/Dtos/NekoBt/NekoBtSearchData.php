<?php

namespace App\Dtos\NekoBt;

use App\Enums\NekoBtLevel;
use App\Enums\NekoBtSort;
use App\Enums\NekoBtVideoCodec;
use App\Enums\NekoBtVideoSource;
use Spatie\LaravelData\Data;

class NekoBtSearchData extends Data
{
    /** The deepest page a search goes to: deep pages are slow on nekoBT, and a search that far is too broad */
    public const MAX_PAGE = 100;

    /**
     * The search. A null yes-or-no filter doesn't filter, and an empty list allows any value.
     *
     * @param  list<NekoBtLevel>  $levels  Any of these levels
     * @param  list<NekoBtVideoSource>  $videoSources  Any of these sources
     * @param  list<NekoBtVideoCodec>  $videoCodecs  Any of these codecs
     * @param  string|null  $audioLanguage  A language code the audio must include, such as `en`
     */
    public function __construct(
        public ?string $search,
        public NekoBtSort $sort = NekoBtSort::Size,
        public bool $descending = true,
        public int $page = 1,
        public ?bool $batch = null,
        public array $levels = [],
        public array $videoSources = [],
        public array $videoCodecs = [],
        public ?string $audioLanguage = null,
        public ?bool $originalTranslation = null,
        public ?bool $machineTranslation = null,
        public ?bool $hardsub = null,
    ) {}

    /**
     * A search from the API's validated query string: `filter[...]`, `sort` (`-size` for descending, the default) and
     * `page[number]`, each optional.
     *
     * @param  array{filter?: array<string, mixed>, sort?: string, page?: array<string, int|string>}  $query
     */
    public static function fromQuery(array $query): self
    {
        $filter = $query['filter'] ?? [];
        $sort = $query['sort'] ?? '-size';
        $search = trim((string) ($filter['search'] ?? ''));

        return new self(
            search: $search !== '' ? $search : null,
            sort: NekoBtSort::from(ltrim($sort, '-')),
            descending: str_starts_with($sort, '-'),
            page: (int) ($query['page']['number'] ?? 1),
            batch: self::parseYesOrNo($filter['batch'] ?? null),
            levels: array_map(fn (string $level): NekoBtLevel => NekoBtLevel::from($level), $filter['levels'] ?? []),
            videoSources: array_map(
                fn (string $source): NekoBtVideoSource => NekoBtVideoSource::from($source),
                $filter['video_sources'] ?? [],
            ),
            videoCodecs: array_map(
                fn (string $codec): NekoBtVideoCodec => NekoBtVideoCodec::from($codec),
                $filter['video_codecs'] ?? [],
            ),
            audioLanguage: $filter['audio_language'] ?? null,
            originalTranslation: self::parseYesOrNo($filter['original_translation'] ?? null),
            machineTranslation: self::parseYesOrNo($filter['machine_translation'] ?? null),
            hardsub: self::parseYesOrNo($filter['hardsub'] ?? null),
        );
    }

    /**
     * A yes-or-no filter from the query string, where it is the text `true` or `false`; missing means no filter.
     */
    private static function parseYesOrNo(?string $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        return $value === 'true';
    }
}
