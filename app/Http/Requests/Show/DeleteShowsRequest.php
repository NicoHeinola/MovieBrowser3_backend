<?php

namespace App\Http\Requests\Show;

use App\Models\Show\Show;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class DeleteShowsRequest extends FormRequest
{
    /**
     * @return array<string, list<string|Exists>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'distinct', Rule::exists(Show::class, 'id')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $ids = $this->input('ids');

        if (!is_array($ids)) {
            return;
        }

        $this->merge([
            'ids' => array_map(static fn (mixed $id): mixed => is_numeric($id) ? (int) $id : $id, $ids),
        ]);
    }
}
