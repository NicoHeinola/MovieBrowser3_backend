<?php

namespace App\Http\Requests\ShowLink;

use App\Enums\ShowLinkType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShowLinkRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'target_show_id' => ['required', 'integer', Rule::exists('shows', 'id')],
            'type' => ['required', 'string', Rule::enum(ShowLinkType::class)],
        ];
    }
}
