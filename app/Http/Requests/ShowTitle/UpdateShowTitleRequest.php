<?php

namespace App\Http\Requests\ShowTitle;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShowTitleRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'is_primary' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
