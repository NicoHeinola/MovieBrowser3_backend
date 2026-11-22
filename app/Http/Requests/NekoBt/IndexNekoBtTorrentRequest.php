<?php

namespace App\Http\Requests\NekoBt;

use App\Dtos\NekoBt\NekoBtSearchData;
use App\Enums\NekoBtLevel;
use App\Enums\NekoBtSort;
use App\Enums\NekoBtVideoCodec;
use App\Enums\NekoBtVideoSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexNekoBtTorrentRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array'],
            'filter.search' => ['nullable', 'string', 'max:255'],
            'filter.batch' => ['sometimes', Rule::in(['true', 'false'])],
            'filter.levels' => ['sometimes', 'array'],
            'filter.levels.*' => [Rule::enum(NekoBtLevel::class)],
            'filter.video_sources' => ['sometimes', 'array'],
            'filter.video_sources.*' => [Rule::enum(NekoBtVideoSource::class)],
            'filter.video_codecs' => ['sometimes', 'array'],
            'filter.video_codecs.*' => [Rule::enum(NekoBtVideoCodec::class)],
            // A language code as nekoBT writes them, such as `en` or `es-419`
            'filter.audio_language' => ['sometimes', 'string', 'max:10', 'regex:/^[a-z]{2,3}(-[a-z0-9]{2,4})?$/'],
            'filter.original_translation' => ['sometimes', Rule::in(['true', 'false'])],
            'filter.machine_translation' => ['sometimes', Rule::in(['true', 'false'])],
            'filter.hardsub' => ['sometimes', Rule::in(['true', 'false'])],
            'sort' => ['sometimes', 'string', Rule::in(NekoBtSort::queryValues())],
            'page' => ['sometimes', 'array'],
            'page.number' => ['sometimes', 'integer', 'min:1', 'max:'.NekoBtSearchData::MAX_PAGE],
        ];
    }
}
