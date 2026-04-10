<?php

namespace App\Http\Requests\Show;

use App\Rules\ExactlyOnePrimaryTitle;
use Illuminate\Foundation\Http\FormRequest;

class UpdateShowRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'banner_url' => ['sometimes', 'required', 'string', 'max:2048'],
            'card_image_url' => ['sometimes', 'required', 'string', 'max:2048'],
            'preview_url' => ['nullable', 'string', 'max:2048'],
            'description' => ['nullable', 'string'],
            'titles' => ['sometimes', 'required', 'array', 'min:1', new ExactlyOnePrimaryTitle],
            'titles.*.title' => ['required_with:titles', 'string', 'max:255', 'distinct:ignore_case'],
            'titles.*.is_primary' => ['required_with:titles', 'boolean'],
        ];
    }
}
