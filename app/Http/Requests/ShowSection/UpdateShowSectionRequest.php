<?php

namespace App\Http\Requests\ShowSection;

use App\Models\Category\Category;
use App\Models\ShowSection\ShowSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShowSectionRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var ShowSection|null $showSection */
        $showSection = $this->route('show_section');

        return [
            'category_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists(Category::class, 'id'),
                Rule::unique('show_sections', 'category_id')->ignore($showSection?->id),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'sort_order' => ['sometimes', 'required', 'integer', 'min:0'],
        ];
    }
}
