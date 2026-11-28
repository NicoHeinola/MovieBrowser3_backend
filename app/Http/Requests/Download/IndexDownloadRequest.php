<?php

namespace App\Http\Requests\Download;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexDownloadRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array:id'],
            // Only these downloads. Ids that don't exist are left out
            'filter.id' => ['sometimes', 'array'],
            'filter.id.*' => ['integer', 'distinct'],
            // Each download with its torrent's files, which takes one qBittorrent request per download
            'include' => ['sometimes', 'string', Rule::in(['files'])],
        ];
    }
}
