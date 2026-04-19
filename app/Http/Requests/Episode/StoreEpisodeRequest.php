<?php

namespace App\Http\Requests\Episode;

use Illuminate\Foundation\Http\FormRequest;

class StoreEpisodeRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'filename' => ['required', 'string', 'max:255'],
            'sequence_number' => ['required', 'integer', 'min:0'],
        ];
    }
}
