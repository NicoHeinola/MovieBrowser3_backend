<?php

namespace App\Http\Requests\ShowCategory;

use App\Models\Show\Show;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreShowCategoryRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var Show $show */
        $show = $this->route('show');

        return [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id'), $this->uniqueCategoryRule($show->id)],
        ];
    }

    private function uniqueCategoryRule(int $showId): Unique
    {
        return Rule::unique('category_show', 'category_id')->where(function ($query) use ($showId) {
            $query->where('show_id', $showId);
        });
    }
}
