<?php

namespace App\Http\Requests\VideoDurationScan;

use App\Models\Show\Show;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class StoreVideoDurationScanRequest extends FormRequest
{
    /**
     * Without `show_ids` every show's episodes are read; with `only_missing` only the episodes without a length.
     *
     * @return array<string, list<string|Exists>>
     */
    public function rules(): array
    {
        return [
            'show_ids' => ['sometimes', 'array', 'min:1'],
            'show_ids.*' => ['required', 'integer', 'distinct', Rule::exists(Show::class, 'id')],
            'only_missing' => ['sometimes', 'boolean'],
        ];
    }
}
