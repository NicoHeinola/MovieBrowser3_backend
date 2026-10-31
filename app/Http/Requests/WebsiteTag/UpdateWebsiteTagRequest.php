<?php

namespace App\Http\Requests\WebsiteTag;

use App\Models\WebsiteTag\WebsiteTag;
use App\Rules\UniqueIgnoringCase;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWebsiteTagRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var WebsiteTag $websiteTag */
        $websiteTag = $this->route('website_tag');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100', new UniqueIgnoringCase('website_tags', 'name', $websiteTag->id)],
        ];
    }
}
