<?php

namespace App\Http\Requests\Download;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDownloadFilesRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'show_id' => ['required', 'integer', 'exists:shows,id'],
            // Empty is allowed: no file picked, the torrent waits
            'files' => ['present', 'array'],
            'files.*.index' => ['required', 'integer', 'min:0', 'distinct'],
            'files.*.episode_id' => ['required', 'integer', 'distinct', 'exists:episodes,id'],
        ];
    }
}
