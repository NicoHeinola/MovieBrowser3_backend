<?php

namespace App\Http\Requests\WebsiteTag;

use App\Rules\UniqueIgnoringCase;
use Illuminate\Foundation\Http\FormRequest;

class StoreWebsiteTagRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', new UniqueIgnoringCase('website_tags', 'name')],
        ];
    }
}
