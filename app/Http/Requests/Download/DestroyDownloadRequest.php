<?php

namespace App\Http\Requests\Download;

use Illuminate\Foundation\Http\FormRequest;

class DestroyDownloadRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            // Also remove the episodes made for the download that are still waiting for their file
            'remove_episodes' => ['sometimes', 'boolean'],
        ];
    }
}
