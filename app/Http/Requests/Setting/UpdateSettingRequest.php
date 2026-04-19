<?php

namespace App\Http\Requests\Setting;

use App\Models\Setting\Setting;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
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
        }

        return $rules;
    }
}
