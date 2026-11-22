<?php

namespace App\Http\Requests\ShowTemplate;

use App\Rules\ShowTemplateUrl;
use Illuminate\Foundation\Http\FormRequest;

class ShowShowTemplateRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'max:2048', 'url', new ShowTemplateUrl],
        ];
    }
}
