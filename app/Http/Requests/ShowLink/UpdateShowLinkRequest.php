<?php

namespace App\Http\Requests\ShowLink;

use App\Enums\ShowLinkType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShowLinkRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'target_show_id' => ['sometimes', 'required', 'integer', Rule::exists('shows', 'id')],
            'type' => ['sometimes', 'required', 'string', Rule::enum(ShowLinkType::class)],
        ];
    }
}
