<?php

namespace App\Http\Requests\ShowTitle;

use Illuminate\Foundation\Http\FormRequest;

class StoreShowTitleRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'is_primary' => ['required', 'boolean'],
        ];
    }
}
