<?php

namespace App\Http\Requests\Category;

use App\Models\Category\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var Category $category */
        $category = $this->route('category');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($category->id)],
            'value' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('categories', 'value')->ignore($category->id)],
            'icon' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
