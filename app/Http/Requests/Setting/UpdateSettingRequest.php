<?php

namespace App\Http\Requests\Setting;

use App\Models\Setting\Setting;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
{
    public function rules(): array
    {
        $key = $this->route('key') ?: $this->route('setting');
        $setting = Setting::where('key', $key)->first();

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
