<?php

namespace App\Http\Requests\Website;

use App\Models\Website\Website;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWebsiteRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var Website $website */
        $website = $this->route('website');

        return [
            'url' => ['sometimes', 'required', 'url:http,https', 'max:2048', Rule::unique('websites', 'url')->ignore($website->id)],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'is_favorite' => ['sometimes', 'boolean'],
            'tag_ids' => ['sometimes', 'array', 'max:50'],
            'tag_ids.*' => ['required', 'integer', 'distinct', Rule::exists('website_tags', 'id')],
        ];
    }
}
