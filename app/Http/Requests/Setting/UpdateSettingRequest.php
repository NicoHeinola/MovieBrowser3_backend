<?php

namespace App\Http\Requests\Setting;

use App\Models\Setting\Setting;
use App\Rules\AbsolutePath;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var Setting|null $setting */
        $setting = $this->route('setting');

        $rules = [
            'value' => ['nullable'],
        ];

        if ($setting) {
            match ($setting->type) {
                'number' => $rules['value'][] = 'numeric',
                'json' => $rules['value'][] = 'array',
                'string' => $rules['value'][] = 'string',
                default => null,
            };

            if ($setting->key === 'video_base_path') {
                $rules['value'] = [
                    'required',
                    'string',
                    new AbsolutePath,
                ];
            }
        }

        return $rules;
    }
}
