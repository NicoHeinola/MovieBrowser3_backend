<?php

namespace App\Http\Requests\Mal;

use Illuminate\Foundation\Http\FormRequest;

class IndexMalAnimeRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'filter' => ['required', 'array'],
            'filter.search' => ['required', 'string', 'max:255'],
        ];
    }
}
