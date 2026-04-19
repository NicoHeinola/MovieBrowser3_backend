<?php

namespace App\Http\Requests\Episode;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEpisodeRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'filename' => ['sometimes', 'required', 'string', 'max:255'],
            'sequence_number' => ['sometimes', 'required', 'integer', 'min:0'],
        ];
    }
}
