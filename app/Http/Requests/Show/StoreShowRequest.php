<?php

namespace App\Http\Requests\Show;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreShowRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'banner_url' => ['required', 'string', 'max:2048'],
            'card_image_url' => ['required', 'string', 'max:2048'],
            'preview_url' => ['nullable', 'string', 'max:2048'],
            'titles' => ['required', 'array', 'min:1'],
            'titles.*.title' => ['required', 'string', 'max:255', 'distinct:ignore_case'],
            'titles.*.is_primary' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $titles = $this->input('titles');

        $this->merge([
            'banner_url' => trim((string) $this->input('banner_url')),
            'card_image_url' => trim((string) $this->input('card_image_url')),
            'preview_url' => $this->filled('preview_url')
                ? trim((string) $this->input('preview_url'))
                : null,
            'titles' => is_array($titles)
                ? array_map(static fn (mixed $title): mixed => is_array($title)
                    ? [
                        'title' => trim((string) ($title['title'] ?? '')),
                        'is_primary' => filter_var($title['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                            ?? $title['is_primary']
                            ?? false,
                    ]
                    : $title, $titles)
                : $titles,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $titles = $this->input('titles', []);

            if (!is_array($titles)) {
                return;
            }

            $primaryTitleCount = collect($titles)
                ->filter(static fn (mixed $title): bool => is_array($title) && ($title['is_primary'] ?? false) === true)
                ->count();

            if ($primaryTitleCount !== 1) {
                $validator->errors()->add('titles', 'Exactly one primary title is required.');
            }
        });
    }
}
