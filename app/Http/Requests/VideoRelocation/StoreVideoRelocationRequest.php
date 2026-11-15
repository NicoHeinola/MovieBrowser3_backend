<?php

namespace App\Http\Requests\VideoRelocation;

use App\Enums\SettingKey;
use App\Rules\VideoBasePath;
use Illuminate\Foundation\Http\FormRequest;

class StoreVideoRelocationRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            SettingKey::VideoBasePath->value => ['bail', 'required', 'string', new VideoBasePath],
        ];
    }
}
