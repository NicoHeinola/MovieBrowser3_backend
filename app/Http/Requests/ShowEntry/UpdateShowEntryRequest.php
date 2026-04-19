<?php

namespace App\Http\Requests\ShowEntry;

use App\Enums\ShowEntryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShowEntryRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'required', 'string', Rule::in(array_column(ShowEntryType::cases(), 'value'))],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'required', 'integer', 'min:1'],
        ];
    }
}
