<?php

namespace App\Http\Requests\Download;

use Illuminate\Foundation\Http\FormRequest;

class StoreDownloadRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            // The info hash finds the torrent in qBittorrent: hex, or base32 in older links
            'magnet_url' => ['required', 'string', 'starts_with:magnet:', 'regex:/xt=urn:btih:([a-f0-9]{40}|[a-z2-7]{32})(&|$)/i'],
            'torrent_url' => ['nullable', 'string', 'url:http,https'],
            // Long release names are shortened when saved, not turned down
            'name' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
