<?php

namespace App\Http\Requests\ShowSection;

use App\Models\Category\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShowSectionRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', Rule::exists(Category::class, 'id'), Rule::unique('show_sections', 'category_id')],
            'description' => ['nullable', 'string'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
