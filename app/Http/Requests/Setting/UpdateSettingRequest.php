<?php

namespace App\Http\Requests\Setting;

use App\Models\Episode\Episode;
use App\Models\Setting\Setting;
use Closure;
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

            if ($setting->key === 'video_base_path') {
                $rules['value'] = [
                    'required',
                    'string',
                    static function (string $attribute, mixed $value, Closure $fail): void {
                        if (!Episode::pathIsAbsolute((string) $value)) {
                            $fail('The video base path must be an absolute path.');
                        }
                    },
                ];
            }
        }

        return $rules;
    }
}
