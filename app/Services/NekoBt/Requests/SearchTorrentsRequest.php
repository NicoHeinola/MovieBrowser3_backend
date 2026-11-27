<?php

namespace App\Services\NekoBt\Requests;

use App\Dtos\NekoBt\NekoBtSearchData;
use App\Enums\NekoBtLevel;
use App\Enums\NekoBtSort;
use App\Enums\NekoBtVideoCodec;
use App\Enums\NekoBtVideoSource;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * One page of torrents matching a search.
 */
final class SearchTorrentsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly NekoBtSearchData $search,
        private readonly int $perPage,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/torrents/search';
    }

    /**
     * @return array<string, string|int>
     */
    protected function defaultQuery(): array
    {
        $filters = [
            'batch' => $this->toYesOrNo($this->search->batch),
            'levels' => $this->joinNekoBtNumbers($this->search->levels),
            'video_type' => $this->joinNekoBtNumbers($this->search->videoSources),
            'video_codec' => $this->joinNekoBtNumbers($this->search->videoCodecs),
            'audio_lang' => $this->search->audioLanguage,
            'otl' => $this->toYesOrNo($this->search->originalTranslation),
            'mtl' => $this->toYesOrNo($this->search->machineTranslation),
            'hardsub' => $this->toYesOrNo($this->search->hardsub),
        ];

        return [
            'query' => $this->search->search ?? '',
            'sort_by' => $this->getSortBy(),
            'limit' => $this->perPage,
            'offset' => ($this->search->page - 1) * $this->perPage,
            // One that isn't set stays out, so nekoBT doesn't filter on it
            ...array_filter($filters, fn (?string $value): bool => $value !== null && $value !== ''),
        ];
    }

    /**
     * @param  list<NekoBtLevel|NekoBtVideoSource|NekoBtVideoCodec>  $values
     * @return string nekoBT's own numbers for them, joined by commas: any of them matches
     */
    private function joinNekoBtNumbers(array $values): string
    {
        return collect($values)->map(fn (NekoBtLevel|NekoBtVideoSource|NekoBtVideoCodec $value): int => $value->getNekoBtNumber())->implode(',');
    }

    /**
     * The only form nekoBT takes a yes-or-no filter in.
     */
    private function toYesOrNo(?bool $value): ?string
    {
        return match ($value) {
            true => 'true',
            false => 'false',
            null => null,
        };
    }

    /**
     * The field's name, with `_asc` for ascending. Its newest-first and oldest-first are named `latest` and `oldest`,
     * and its size field `filesize`.
     */
    private function getSortBy(): string
    {
        $isDescending = $this->search->descending;

        if ($this->search->sort === NekoBtSort::Best) {
            return 'best';
        }

        if ($this->search->sort === NekoBtSort::Date) {
            return $isDescending ? 'latest' : 'oldest';
        }

        $field = $this->search->sort === NekoBtSort::Size ? 'filesize' : $this->search->sort->value;

        return $isDescending ? $field : "{$field}_asc";
    }
}
