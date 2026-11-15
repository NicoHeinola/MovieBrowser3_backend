<?php

namespace App\Http\Requests\Setting;

use App\Enums\SettingKey;
use App\Models\Setting\Setting;
use App\Rules\AbsolutePath;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

            if ($setting->key === SettingKey::VideoBasePath->value) {
                $rules['value'] = [
                    'required',
                    'string',
                    new AbsolutePath,
                ];
            }

            // Empty means `vlc` on the PATH. `vlc` itself stays valid: GET /setup detects it and the wizard saves it
            if ($setting->key === SettingKey::VlcMediaPlayerPath->value) {
                $rules['value'] = [
                    'nullable',
                    'string',
                    Rule::when($this->input('value') !== 'vlc', [new AbsolutePath]),
                ];
            }

            // Read back as a date by the setup wizard; empty means "not set up"
            if ($setting->key === SettingKey::SetupCompletedAt->value) {
                $rules['value'] = ['nullable', 'date'];
            }
        }

        return $rules;
    }
}
