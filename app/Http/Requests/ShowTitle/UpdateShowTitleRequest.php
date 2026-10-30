<?php

namespace App\Http\Requests\ShowTitle;

use App\Models\ShowTitle\ShowTitle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShowTitleRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var ShowTitle $title */
        $title = $this->route('title');

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            // A show keeps exactly one primary title; promote another title instead of demoting this one.
            'is_primary' => ['sometimes', 'required', 'boolean', Rule::when($title->is_primary, ['accepted'])],
        ];
    }
}
