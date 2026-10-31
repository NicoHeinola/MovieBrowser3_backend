<?php

namespace App\Http\Requests\Website;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWebsiteRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'url:http,https', 'max:2048', Rule::unique('websites', 'url')],
            'title' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'tags' => ['sometimes', 'array', 'max:50'],
            'tags.*' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
        ];
    }
}
