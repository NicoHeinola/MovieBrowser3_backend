<?php

namespace App\Http\Requests\VideoBaseFolderMove;

use App\Enums\SettingKey;
use App\Rules\MediaFolderPath;
use Illuminate\Foundation\Http\FormRequest;

class StoreVideoBaseFolderMoveRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            SettingKey::VideoBaseFolder->value => ['bail', 'required', 'string', new MediaFolderPath],
        ];
    }
}
