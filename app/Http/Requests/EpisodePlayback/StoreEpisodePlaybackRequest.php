<?php

namespace App\Http\Requests\EpisodePlayback;

use Illuminate\Foundation\Http\FormRequest;

class StoreEpisodePlaybackRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'from_start' => ['sometimes', 'boolean'],
        ];
    }
}
