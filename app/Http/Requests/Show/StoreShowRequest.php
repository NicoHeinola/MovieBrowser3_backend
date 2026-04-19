<?php

namespace App\Http\Requests\Show;

use Illuminate\Foundation\Http\FormRequest;

class StoreShowRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'banner_url' => ['required', 'string', 'max:2048'],
            'card_image_url' => ['required', 'string', 'max:2048'],
            'preview_url' => ['nullable', 'string', 'max:2048'],
            'description' => ['nullable', 'string'],
        ];
    }
}
