<?php

namespace App\Http\Requests\Show;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateShowRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'banner_url' => ['sometimes', 'required', 'string', 'max:2048'],
            'card_image_url' => ['sometimes', 'required', 'string', 'max:2048'],
            'preview_url' => ['nullable', 'string', 'max:2048'],
            'titles' => ['sometimes', 'required', 'array', 'min:1'],
            'titles.*.title' => ['required_with:titles', 'string', 'max:255', 'distinct:ignore_case'],
            'titles.*.is_primary' => ['required_with:titles', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->exists('banner_url')) {
            $payload['banner_url'] = trim((string) $this->input('banner_url'));
        }

        if ($this->exists('card_image_url')) {
            $payload['card_image_url'] = trim((string) $this->input('card_image_url'));
        }

        if ($this->exists('preview_url')) {
            $payload['preview_url'] = $this->filled('preview_url')
                ? trim((string) $this->input('preview_url'))
                : null;
        }

        if ($this->exists('titles')) {
            $titles = $this->input('titles');

            $payload['titles'] = is_array($titles)
                ? array_map(static fn (mixed $title): mixed => is_array($title)
                    ? [
                        'title' => trim((string) ($title['title'] ?? '')),
                        'is_primary' => filter_var($title['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                            ?? $title['is_primary']
                            ?? false,
                    ]
                    : $title, $titles)
                : $titles;
        }

        $this->merge($payload);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (!$this->exists('titles')) {
                return;
            }

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
